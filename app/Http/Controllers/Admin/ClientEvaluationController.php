<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ClientEvaluationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreClientEvaluationRequest;
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
        $this->invites->expireOverdue();

        $q = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();
        $statusFilter = ClientEvaluationStatus::tryFrom($status);

        $evaluations = ClientEvaluation::query()
            ->with('company:id,name')
            ->when($statusFilter !== null, fn ($query) => $query->where('status', $statusFilter))
            ->when($q !== '', function ($query) use ($q): void {
                $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $query->where(function ($inner) use ($q, $operator): void {
                    $inner->where('respondent_name', $operator, '%'.$q.'%')
                        ->orWhere('respondent_email', $operator, '%'.$q.'%')
                        ->orWhereHas('company', fn ($company) => $company->where('name', $operator, '%'.$q.'%'));
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $evaluations->getCollection()->transform(
            fn (ClientEvaluation $evaluation): array => $evaluation->toListArray(),
        );

        return Inertia::render('Admin/ClientEvaluations/Index', [
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
        $companyId = $request->integer('company_id');
        $year = $request->integer('cycle_year') ?: (int) now()->year;
        $quarter = $request->integer('cycle_quarter') ?: (int) ceil(now()->month / 3);

        $companyExists = $companyId > 0 && Company::query()->whereKey($companyId)->exists();

        return Inertia::render('Admin/ClientEvaluations/Create', [
            'companies' => Company::query()
                ->orderBy('name')
                ->get(['id', 'name', 'contact_email'])
                ->map(fn (Company $company): array => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'contact_email' => $company->contact_email,
                ])
                ->values()
                ->all(),
            'defaults' => [
                'company_id' => $companyExists ? $companyId : null,
                'cycle_year' => $year,
                'cycle_quarter' => max(1, min(4, $quarter)),
                'expires_at' => now()->addDays(30)->toDateString(),
            ],
        ]);
    }

    public function store(StoreClientEvaluationRequest $request): RedirectResponse
    {
        $evaluation = $this->invites->create($request->validated(), $request->user());

        return redirect()
            ->route('admin.client-evaluations.show', $evaluation)
            ->with('success', 'Avaliação criada. Envie o link para o cliente.');
    }

    public function show(ClientEvaluation $evaluation): Response
    {
        $evaluation = $this->invites->refresh($evaluation);
        $evaluation->load('company:id,name');

        return Inertia::render('Admin/ClientEvaluations/Show', [
            'evaluation' => $evaluation->toAdminDetail(),
        ]);
    }

    public function sendEmail(ClientEvaluation $evaluation): RedirectResponse
    {
        $evaluation = $this->invites->refresh($evaluation);

        if (! $evaluation->isPendingOpen() || ! filled($evaluation->respondent_email)) {
            return redirect()
                ->route('admin.client-evaluations.show', $evaluation)
                ->with('error', 'Esta avaliação não pode mais ser enviada por e-mail.');
        }

        $evaluation->loadMissing('company:id,name');

        try {
            Mail::to($evaluation->respondent_email)->send(new ClientEvaluationInvitationMail($evaluation));
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->route('admin.client-evaluations.show', $evaluation)
                ->with('error', 'Não foi possível enviar o e-mail. Confira a configuração de SMTP e tente de novo.');
        }

        $evaluation->update(['sent_at' => now()]);

        return redirect()
            ->route('admin.client-evaluations.show', $evaluation)
            ->with('success', 'E-mail enviado para '.$evaluation->respondent_email.'.');
    }
}
