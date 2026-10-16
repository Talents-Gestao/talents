<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Enums\ClientEvaluationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreClientEvaluationRequest;
use App\Mail\ClientEvaluationInvitationMail;
use App\Models\ClientEvaluation;
use App\Models\Company;
use App\Services\ClientEvaluation\ClientEvaluationInviteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class ClientEvaluationController extends Controller
{
    public function __construct(
        private readonly ClientEvaluationInviteService $invites,
    ) {}

    public function index(Request $request): Response
    {
        $company = $this->company($request);
        $this->invites->expireOverdue();

        $q = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();
        $statusFilter = ClientEvaluationStatus::tryFrom($status);

        $evaluations = ClientEvaluation::query()
            ->with('company:id,name')
            ->where('company_id', $company->id)
            ->when($statusFilter !== null, fn ($query) => $query->where('status', $statusFilter))
            ->when($q !== '', function ($query) use ($q): void {
                $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $query->where(function ($inner) use ($q, $operator): void {
                    $inner->where('respondent_name', $operator, '%'.$q.'%')
                        ->orWhere('respondent_email', $operator, '%'.$q.'%');
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $evaluations->getCollection()->transform(
            fn (ClientEvaluation $evaluation): array => $evaluation->toListArray(),
        );

        return Inertia::render('Client/Evaluations/Index', [
            'companyName' => $company->name,
            'evaluations' => $evaluations,
            'filters' => [
                'q' => $q,
                'status' => $statusFilter?->value ?? '',
            ],
            'statuses' => collect(ClientEvaluationStatus::cases())
                ->map(fn (ClientEvaluationStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function create(Request $request): Response
    {
        $company = $this->company($request);
        $year = $request->integer('cycle_year') ?: (int) now()->year;
        $quarter = $request->integer('cycle_quarter') ?: (int) ceil(now()->month / 3);

        return Inertia::render('Client/Evaluations/Create', [
            'company' => [
                'name' => $company->name,
                'contact_email' => $company->contact_email,
            ],
            'defaults' => [
                'cycle_year' => $year,
                'cycle_quarter' => max(1, min(4, $quarter)),
                'expires_at' => now()->addDays(30)->toDateString(),
                'respondent_email' => $company->contact_email ?? '',
            ],
        ]);
    }

    public function store(StoreClientEvaluationRequest $request): RedirectResponse
    {
        $company = $this->company($request);
        $evaluation = $this->invites->create([
            ...$request->validated(),
            'company_id' => $company->id,
        ], $request->user());

        return redirect()
            ->route('client.avaliacoes.show', $evaluation)
            ->with('success', 'Convite criado. Envie o link para quem vai responder.');
    }

    public function show(Request $request, ClientEvaluation $evaluation): Response
    {
        $evaluation = $this->invites->refresh($this->evaluationFor($request, $evaluation));
        $evaluation->load('company:id,name');

        return Inertia::render('Client/Evaluations/Show', [
            'evaluation' => $evaluation->toAdminDetail(),
        ]);
    }

    public function sendEmail(Request $request, ClientEvaluation $evaluation): RedirectResponse
    {
        $evaluation = $this->invites->refresh($this->evaluationFor($request, $evaluation));

        if (! $evaluation->isPendingOpen() || ! filled($evaluation->respondent_email)) {
            return redirect()
                ->route('client.avaliacoes.show', $evaluation)
                ->with('error', 'Este convite não pode mais ser enviado por e-mail.');
        }

        $evaluation->loadMissing('company:id,name');

        try {
            Mail::to($evaluation->respondent_email)->send(new ClientEvaluationInvitationMail($evaluation));
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->route('client.avaliacoes.show', $evaluation)
                ->with('error', 'Não foi possível enviar o e-mail. Tente de novo em alguns instantes.');
        }

        $evaluation->update(['sent_at' => now()]);

        return redirect()
            ->route('client.avaliacoes.show', $evaluation)
            ->with('success', 'E-mail enviado para '.$evaluation->respondent_email.'.');
    }

    private function company(Request $request): Company
    {
        $company = $request->user()?->contextCompany();
        abort_unless($company, 404);

        return $company;
    }

    private function evaluationFor(Request $request, ClientEvaluation $evaluation): ClientEvaluation
    {
        abort_unless($evaluation->company_id === $this->company($request)->id, 404);

        return $evaluation;
    }
}
