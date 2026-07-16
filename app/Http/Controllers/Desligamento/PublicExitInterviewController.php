<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desligamento;

use App\Enums\ExitInterviewStatus;
use App\Http\Controllers\Controller;
use App\Models\ExitInterview;
use App\Support\Desligamento\ExitInterviewScript;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class PublicExitInterviewController extends Controller
{
    public function show(string $token): Response
    {
        $interview = $this->findByToken($token);

        if (! $interview->acceptsEmployeeResponses()) {
            return Inertia::render('Desligamento/Public/Closed', [
                'message' => $this->closedMessage($interview),
                'employeeName' => $interview->collaboratorDisplayName(),
                'companyName' => $interview->company?->name,
            ]);
        }

        return Inertia::render('Desligamento/Public/Take', [
            'token' => $interview->public_token,
            'employeeName' => $interview->collaboratorDisplayName(),
            'companyName' => $interview->company?->name,
            'sections' => ExitInterviewScript::sections(),
            'answers' => $interview->answers ?? [],
        ]);
    }

    public function submit(Request $request, string $token): RedirectResponse
    {
        $interview = $this->findByToken($token);

        abort_unless($interview->acceptsEmployeeResponses(), 403);

        $answerRules = [];
        foreach (ExitInterviewScript::answerKeys() as $key) {
            $answerRules["answers.{$key}"] = ['nullable', 'string', 'max:20000'];
        }

        $data = $request->validate([
            'answers' => ['nullable', 'array'],
            ...$answerRules,
        ]);

        $answers = [];
        foreach (ExitInterviewScript::answerKeys() as $key) {
            $value = trim((string) ($data['answers'][$key] ?? ''));
            if ($value !== '') {
                $answers[$key] = $value;
            }
        }

        abort_if($answers === [], 422, 'Preencha ao menos uma resposta antes de enviar.');

        $interview->update([
            'answers' => $answers,
            'status' => ExitInterviewStatus::Completed,
            'interview_date' => $interview->interview_date ?? Carbon::today(),
            'employee_submitted_at' => now(),
        ]);

        return redirect()
            ->route('desligamento.public.thanks', $token)
            ->with('success', 'Obrigado! Suas respostas foram registradas.');
    }

    public function thanks(string $token): Response
    {
        $interview = $this->findByToken($token);

        return Inertia::render('Desligamento/Public/ThankYou', [
            'employeeName' => $interview->collaboratorDisplayName(),
            'companyName' => $interview->company?->name,
        ]);
    }

    private function findByToken(string $token): ExitInterview
    {
        return ExitInterview::query()
            ->where('public_token', $token)
            ->with(['company', 'employee:id,name,email'])
            ->firstOrFail();
    }

    private function closedMessage(ExitInterview $interview): string
    {
        if (! $interview->company?->hasDesligamentoEnabled()) {
            return 'Este link não está disponível no momento.';
        }

        if ($interview->employee_submitted_at !== null || $interview->status === ExitInterviewStatus::Completed) {
            return 'Esta pesquisa já foi respondida. Obrigado!';
        }

        return 'Este link não está mais ativo.';
    }
}
