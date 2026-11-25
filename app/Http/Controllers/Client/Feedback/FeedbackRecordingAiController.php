<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\Feedback;

use App\Actions\Feedback\MapFeedbackRecordingSpeakers;
use App\Actions\Feedback\RewindFeedbackRecording;
use App\Actions\Feedback\StartFeedbackRecordingProcessing;
use App\Actions\Feedback\UpdateFeedbackCleanTranscript;
use App\Http\Controllers\Concerns\ResolvesFeedbackRoutes;
use App\Models\Company;
use App\Models\FeedbackAuditLog;
use App\Models\FeedbackRecording;
use App\Models\FeedbackSession;
use App\Services\Feedback\Ai\FeedbackAiException;
use App\Support\Feedback\FeedbackRecordingAccess;
use App\Support\Feedback\FeedbackVisibility;
use App\Support\Feedback\TranscriptSpeakers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * IA da gravação do feedback: mapear falantes, processar (limpeza + relatório), tentar de novo, gerar o
 * relatório novamente e editar a transcrição limpa. Portal e admin (empresa selecionada) usam este controller;
 * a sessão passa sempre por authorizeSession e a sessão concluída ou cancelada fica só para leitura.
 */
class FeedbackRecordingAiController extends FeedbackCompanyController
{
    use ResolvesFeedbackRoutes;

    public const MAX_CLEAN_CHARS = 1_000_000;

    public function speakers(Request $request, FeedbackSession $session, MapFeedbackRecordingSpeakers $map): RedirectResponse
    {
        $recording = $this->recording($request, $session);
        abort_unless(FeedbackRecordingAccess::canProcess($request, $session), 403);

        $data = $request->validate(
            ['speakers' => ['required', 'array', 'min:1', 'max:8'], 'speakers.*' => ['required', 'string']],
            ['speakers.required' => 'Escolha o papel de cada pessoa da transcrição.', 'speakers.*.required' => 'Escolha o papel de cada pessoa da transcrição.'],
        );

        $map->execute($recording, $request->user(), $data['speakers']);

        return back()->with('success', 'Falantes confirmados.');
    }

    public function process(Request $request, FeedbackSession $session, StartFeedbackRecordingProcessing $start): RedirectResponse
    {
        return $this->start($request, $session, $start, StartFeedbackRecordingProcessing::REQUEST_PROCESS, 'Processamento iniciado. O resultado aparece aqui quando ficar pronto.');
    }

    public function retry(Request $request, FeedbackSession $session, StartFeedbackRecordingProcessing $start): RedirectResponse
    {
        return $this->start($request, $session, $start, StartFeedbackRecordingProcessing::REQUEST_RETRY, 'Nova tentativa iniciada.');
    }

    public function regenerateReport(Request $request, FeedbackSession $session, StartFeedbackRecordingProcessing $start): RedirectResponse
    {
        return $this->start($request, $session, $start, StartFeedbackRecordingProcessing::REQUEST_REPORT, 'Novo relatório em geração. O anterior continua guardado.');
    }

    /**
     * Voltar uma etapa na barra «Gravação» (RewindFeedbackRecording). Mesmas permissões das ações reaproveitadas:
     * «Enviar» exige excluir a gravação; «Confirmar falantes» e «Processar», processar. Nunca com assinatura
     * solicitada ou concluída, nem durante o processamento.
     */
    public function rewind(Request $request, FeedbackSession $session, RewindFeedbackRecording $rewind): RedirectResponse
    {
        $recording = $this->recording($request, $session);

        $data = $request->validate([
            'step' => ['required', 'string', Rule::in(RewindFeedbackRecording::TARGETS)],
            'confirmed' => ['accepted'],
            'speakers' => ['required_if:step,'.RewindFeedbackRecording::STEP_SPEAKERS, 'array', 'max:8'],
            'speakers.*' => ['required', 'string'],
        ], [
            'step.*' => 'Etapa inválida.',
            'confirmed.accepted' => 'Confirme a volta de etapa.',
            'speakers.required_if' => 'Escolha o papel de cada pessoa da transcrição.',
            'speakers.*.required' => 'Escolha o papel de cada pessoa da transcrição.',
        ]);

        $step = $data['step'];
        abort_unless($step === RewindFeedbackRecording::STEP_UPLOAD
            ? FeedbackRecordingAccess::canDelete($request, $session)
            : FeedbackRecordingAccess::canProcess($request, $session), 403);

        if ($reason = FeedbackRecordingAccess::rewindBlockedReason($session)) {
            abort(403, $reason);
        }
        if ($recording->processing_status === FeedbackRecording::STATUS_PROCESSING) {
            return back()->withErrors(['step' => FeedbackRecordingAccess::REWIND_PROCESSING_MESSAGE]);
        }

        try {
            $rewind->execute($recording, $request->user(), $step, $data['speakers'] ?? []);
        } catch (FeedbackAiException $e) {
            return back()->withErrors(['ai' => $e->getMessage()]);
        }

        return back()->with('success', match ($step) {
            RewindFeedbackRecording::STEP_UPLOAD => 'Gravação excluída. Envie o novo arquivo.',
            RewindFeedbackRecording::STEP_SPEAKERS => 'Falantes confirmados. O relatório anterior foi para o histórico; processe de novo.',
            default => 'Novo relatório em geração. O anterior continua guardado.',
        });
    }

    /**
     * «Ver trechos» de «Confirmar falantes» (JSON sob demanda, sem cache): número de falas e até 3 frases mais longas
     * de cada falante, para quem pode mapear os falantes. Fica fora do HTML da página; cada abertura vai para a trilha.
     */
    public function samples(Request $request, FeedbackSession $session): JsonResponse
    {
        $recording = $this->recording($request, $session);
        abort_unless(FeedbackRecordingAccess::canProcess($request, $session) && $recording->has_speaker_labels, 403);

        $speakers = array_map(fn (array $u) => [
            'label' => $u['label'],
            'count' => count($u['utterances']),
            'samples' => TranscriptSpeakers::representative($u['utterances']),
        ], TranscriptSpeakers::utterances($recording->safeOriginalText()));

        FeedbackAuditLog::query()->create([
            'company_id' => $session->company_id,
            'user_id' => $request->user()->id,
            'action' => FeedbackAuditLog::ACTION_RECORDING_SAMPLES_VIEWED,
            'subject_type' => FeedbackAuditLog::SUBJECT_RECORDING,
            'subject_id' => $recording->id,
            'subject_label' => $session->title,
            'details' => ['feedback_session_id' => $session->id, 'speakers' => count($speakers)],
        ]);

        return response()->json(['speakers' => $speakers])->header('Cache-Control', 'no-store, private');
    }

    public function updateClean(Request $request, FeedbackSession $session, UpdateFeedbackCleanTranscript $update): RedirectResponse
    {
        $recording = $this->recording($request, $session);
        abort_unless(FeedbackRecordingAccess::canEditClean($request, $session), 403);

        $data = $request->validate([
            'clean_text' => ['required', 'string', 'max:'.self::MAX_CLEAN_CHARS],
            'revision' => ['required', 'integer', 'min:0'],
        ], [
            'clean_text.required' => 'A transcrição limpa não pode ficar vazia.',
            'clean_text.max' => 'A transcrição limpa passa do limite de 1.000.000 caracteres.',
        ]);

        $update->execute($recording, $request->user(), trim($data['clean_text']), (int) $data['revision']);

        return back()->with('success', 'Transcrição limpa salva. Para refletir no relatório, use «Gerar relatório novamente».');
    }

    private function start(Request $request, FeedbackSession $session, StartFeedbackRecordingProcessing $start, string $kind, string $success): RedirectResponse
    {
        $recording = $this->recording($request, $session);
        abort_unless(FeedbackRecordingAccess::canProcess($request, $session), 403);

        try {
            $start->execute($recording, $request->user(), $kind);
        } catch (FeedbackAiException $e) {
            return back()->withErrors(['ai' => $e->getMessage()]);
        }

        return back()->with('success', $success);
    }

    /** Sessão da empresa e visível ao usuário, aberta, com gravação. */
    private function recording(Request $request, FeedbackSession $session): FeedbackRecording
    {
        $this->authorizeSession($this->company($request), $session);
        abort_if(FeedbackRecordingAccess::isLocked($session), 403, 'Feedback concluído ou cancelado: a gravação e o relatório ficam só para leitura.');

        $recording = $session->currentRecording();
        abort_if($recording === null, 404);

        return $recording;
    }

    private function authorizeSession(Company $company, FeedbackSession $session): void
    {
        abort_unless($session->company_id === $company->id, 403);
        FeedbackVisibility::authorizeSession(request()->user(), $session);
    }
}
