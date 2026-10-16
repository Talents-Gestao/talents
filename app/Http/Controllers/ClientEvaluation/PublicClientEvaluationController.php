<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClientEvaluation;

use App\Enums\ClientEvaluationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClientEvaluation\SubmitClientEvaluationRequest;
use App\Models\ClientEvaluation;
use App\Services\ClientEvaluation\ClientEvaluationInviteService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PublicClientEvaluationController extends Controller
{
    public function __construct(
        private readonly ClientEvaluationInviteService $invites,
    ) {}

    public function show(string $token): InertiaResponse
    {
        $evaluation = $this->invites->refresh($this->find($token));
        $evaluation->load('company:id,name');

        if ($evaluation->status !== ClientEvaluationStatus::Pending) {
            return Inertia::render('ClientEvaluation/Unavailable', [
                'message' => $evaluation->status->unavailableMessage(),
            ]);
        }

        return Inertia::render('ClientEvaluation/Take', [
            'evaluation' => [
                'company_name' => $evaluation->company?->name,
                'cycle_label' => $evaluation->cycleLabel(),
                'respondent_name' => $evaluation->respondent_name,
                'public_token' => $evaluation->public_token,
            ],
        ]);
    }

    public function thanks(string $token): InertiaResponse|RedirectResponse
    {
        $evaluation = $this->find($token);
        $evaluation->load('company:id,name');

        if ($evaluation->status !== ClientEvaluationStatus::Answered) {
            return redirect()->route('client-evaluation.public', $token);
        }

        return Inertia::render('ClientEvaluation/ThankYou', [
            'companyName' => $evaluation->company?->name,
        ]);
    }

    public function submit(SubmitClientEvaluationRequest $request, string $token): RedirectResponse
    {
        $evaluation = $this->find($token);
        $this->invites->answer($evaluation, $request->validated());

        return redirect()->route('client-evaluation.public.thanks', $token);
    }

    private function find(string $token): ClientEvaluation
    {
        return ClientEvaluation::query()->where('public_token', $token)->firstOrFail();
    }
}
