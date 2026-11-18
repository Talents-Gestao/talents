<?php

declare(strict_types=1);

namespace App\Actions\Feedback;

use App\Jobs\ProcessFeedbackRecordingJob;
use App\Models\FeedbackAuditLog;
use App\Models\FeedbackRecording;
use App\Models\User;
use App\Services\Feedback\Ai\FeedbackAiException;
use App\Support\Feedback\Ai\FeedbackAiGate;
use App\Support\Feedback\Ai\FeedbackAiMonthlyLimit;
use Illuminate\Support\Facades\DB;

/**
 * Coloca a gravação na fila de processamento por IA: processar (limpeza + relatório), tentar de novo depois de
 * uma falha (continua de onde parou: se a limpeza já existe, refaz só o relatório) ou gerar o relatório novamente.
 * Antes de enfileirar, confere a trava do provedor aprovado e o limite mensal da empresa.
 */
class StartFeedbackRecordingProcessing
{
    public const REQUEST_PROCESS = 'process';

    public const REQUEST_RETRY = 'retry';

    public const REQUEST_REPORT = 'report';

    public function __construct(
        private readonly FeedbackAiGate $gate,
        private readonly FeedbackAiMonthlyLimit $limit,
    ) {}

    /** @throws FeedbackAiException */
    public function execute(FeedbackRecording $recording, User $actor, string $request): FeedbackRecording
    {
        $this->gate->assertAllowed();
        $recording->loadMissing('session.company');
        $this->limit->assertWithin($recording->session->company);

        return DB::transaction(function () use ($recording, $actor, $request) {
            /** @var FeedbackRecording $locked */
            $locked = FeedbackRecording::query()->whereKey($recording->id)->lockForUpdate()->firstOrFail();

            if ($locked->processing_status === FeedbackRecording::STATUS_PROCESSING) {
                throw new FeedbackAiException('A gravação já está sendo processada. Aguarde.', 'already_processing');
            }

            $mode = match ($request) {
                self::REQUEST_PROCESS => $this->processMode($locked),
                self::REQUEST_RETRY => $this->retryMode($locked),
                self::REQUEST_REPORT => $this->reportMode($locked),
            };

            if ($mode === FeedbackRecording::MODE_FULL && $locked->has_speaker_labels && empty($locked->speaker_map)) {
                throw new FeedbackAiException('Confirme quem é o líder e quem é o colaborador antes de processar.', 'speakers_not_mapped');
            }

            $locked->forceFill([
                'processing_status' => FeedbackRecording::STATUS_PROCESSING,
                'processing_mode' => $mode,
                'processing_stage' => $mode === FeedbackRecording::MODE_FULL ? FeedbackRecording::STAGE_CLEANING : FeedbackRecording::STAGE_REPORT,
                'processing_chunks_done' => 0,
                'processing_chunks_total' => 0,
                'processing_error' => null,
                'processing_attempts' => (int) $locked->processing_attempts + 1,
                'processing_requested_by_user_id' => $actor->id,
                'processing_started_at' => now(),
            ])->save();

            FeedbackAuditLog::query()->create([
                'company_id' => $locked->company_id,
                'user_id' => $actor->id,
                'action' => FeedbackAuditLog::ACTION_RECORDING_PROCESSING_REQUESTED,
                'subject_type' => FeedbackAuditLog::SUBJECT_RECORDING,
                'subject_id' => $locked->id,
                'subject_label' => $recording->session->title,
                'details' => [
                    'feedback_session_id' => $locked->feedback_session_id,
                    'request' => $request,
                    'mode' => $mode,
                    'attempt' => $locked->processing_attempts,
                ] + $this->gate->assertAllowed(),
            ]);

            ProcessFeedbackRecordingJob::dispatch($locked->id, $mode, $actor->id)->afterCommit();

            return $locked;
        });
    }

    private function processMode(FeedbackRecording $recording): string
    {
        if ($recording->processing_status !== FeedbackRecording::STATUS_UPLOADED) {
            throw new FeedbackAiException('Esta gravação já foi processada. Use «Gerar relatório novamente» ou «Tentar de novo».', 'invalid_state');
        }

        return FeedbackRecording::MODE_FULL;
    }

    private function retryMode(FeedbackRecording $recording): string
    {
        if ($recording->processing_status !== FeedbackRecording::STATUS_FAILED) {
            throw new FeedbackAiException('Só é possível tentar de novo depois de uma falha.', 'invalid_state');
        }

        return $recording->hasCleanText() ? FeedbackRecording::MODE_REPORT : FeedbackRecording::MODE_FULL;
    }

    private function reportMode(FeedbackRecording $recording): string
    {
        if (! $recording->hasCleanText() || ! in_array($recording->processing_status, [FeedbackRecording::STATUS_READY, FeedbackRecording::STATUS_FAILED], true)) {
            throw new FeedbackAiException('O relatório só pode ser gerado de novo depois que a transcrição limpa estiver pronta.', 'invalid_state');
        }

        return FeedbackRecording::MODE_REPORT;
    }
}
