<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\FeedbackRecording;
use App\Models\User;
use App\Services\Feedback\Ai\FeedbackAiException;
use App\Services\Feedback\Ai\FeedbackReportGenerator;
use App\Services\Feedback\Ai\FeedbackTranscriptCleaner;
use App\Support\Feedback\FeedbackRecordingAccess;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Processa a gravação do feedback na fila: modo «full» limpa a transcrição e gera o relatório; modo «report»
 * só gera um novo relatório a partir da transcrição limpa atual. Estado: processing → ready | failed, com a
 * mensagem em português para o usuário (detalhe técnico só no log). Nunca toca nas respostas do feedback.
 */
class ProcessFeedbackRecordingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public const GENERIC_ERROR = 'Não foi possível processar a gravação. Tente de novo; se continuar, fale com a Talents.';

    public function __construct(
        public readonly int $recordingId,
        public readonly string $mode,
        public readonly ?int $actorId = null,
    ) {}

    public function handle(FeedbackTranscriptCleaner $cleaner, FeedbackReportGenerator $generator): void
    {
        $recording = FeedbackRecording::query()->with('session')->find($this->recordingId);
        if ($recording === null || $recording->processing_status !== FeedbackRecording::STATUS_PROCESSING || $recording->session === null) {
            return; // excluída, ou já tratada por outra execução
        }

        if (FeedbackRecordingAccess::isLocked($recording->session)) {
            $this->markFailed($recording, 'Feedback concluído ou cancelado: o processamento foi interrompido.', 'locked');

            return;
        }

        $actor = $this->actorId ? User::query()->find($this->actorId) : null;

        try {
            if ($this->mode === FeedbackRecording::MODE_FULL) {
                $recording->forceFill(['processing_stage' => FeedbackRecording::STAGE_CLEANING])->save();

                $clean = $cleaner->clean($recording, $actor, function (int $done, int $total) use ($recording) {
                    $recording->forceFill(['processing_chunks_done' => $done, 'processing_chunks_total' => $total])->save();
                });

                $recording->forceFill([
                    'clean_text' => $clean,
                    'clean_generated_at' => now(),
                    'clean_revision' => (int) $recording->clean_revision + 1,
                    'clean_edited_by_user_id' => null,
                    'clean_edited_at' => null,
                ])->save();
            }

            $recording->forceFill(['processing_stage' => FeedbackRecording::STAGE_REPORT])->save();
            $generator->generate($recording, $actor);

            $recording->forceFill([
                'processing_status' => FeedbackRecording::STATUS_READY,
                'processing_stage' => null,
                'processing_error' => null,
                'processed_at' => now(),
            ])->save();
        } catch (FeedbackAiException $e) {
            Log::warning('Feedbacks IA: processamento falhou', ['recording_id' => $recording->id, 'mode' => $this->mode, 'code' => $e->errorCode]);
            $this->markFailed($recording, $e->getMessage(), $e->errorCode);
        } catch (Throwable $e) {
            report($e);
            $this->markFailed($recording, self::GENERIC_ERROR, 'exception');
        }
    }

    /** Tempo esgotado ou erro fora do handle: a gravação não fica presa em «processando». */
    public function failed(?Throwable $e): void
    {
        $recording = FeedbackRecording::query()->find($this->recordingId);
        if ($recording !== null && $recording->processing_status === FeedbackRecording::STATUS_PROCESSING) {
            if ($e !== null) {
                Log::warning('Feedbacks IA: job falhou', ['recording_id' => $this->recordingId, 'error' => mb_substr($e->getMessage(), 0, 300)]);
            }
            $this->markFailed($recording, self::GENERIC_ERROR, 'job_failed');
        }
    }

    private function markFailed(FeedbackRecording $recording, string $message, string $code): void
    {
        $recording->forceFill([
            'processing_status' => FeedbackRecording::STATUS_FAILED,
            'processing_error' => mb_substr($message, 0, 500),
        ])->save();
    }
}
