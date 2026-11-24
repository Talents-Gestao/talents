<?php

declare(strict_types=1);

namespace App\Actions\Feedback;

use App\Models\FeedbackAuditLog;
use App\Models\FeedbackRecording;
use App\Models\User;
use App\Services\Feedback\Ai\FeedbackAiException;
use App\Support\Feedback\FeedbackRecordingAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Volta uma etapa da barra «Gravação» (Enviar › Confirmar falantes › Processar › Revisar relatório) reaproveitando
 * as ações de sempre e registrando a volta em feedback_audit_logs (quem, quando, de qual para qual etapa):
 * - speakers: novo mapeamento; o relatório atual vira versão antiga (não é apagado), a transcrição limpa feita com
 *   o mapeamento anterior sai e a gravação volta para «Processar» (MapFeedbackRecordingSpeakers);
 * - upload: exclui a gravação como «Excluir gravação» (DeleteFeedbackRecording) para enviar outro arquivo;
 * - process: gera o relatório novamente (StartFeedbackRecordingProcessing: trava do provedor, limite e uso).
 * Permissão e sessão (assinatura, concluída) ficam no controller; aqui, o estado da gravação.
 */
class RewindFeedbackRecording
{
    public const STEP_UPLOAD = 'upload';

    public const STEP_SPEAKERS = 'speakers';

    public const STEP_PROCESS = 'process';

    public const STEP_REVIEW = 'review';

    public const TARGETS = [self::STEP_UPLOAD, self::STEP_SPEAKERS, self::STEP_PROCESS];

    public function __construct(
        private readonly MapFeedbackRecordingSpeakers $map,
        private readonly DeleteFeedbackRecording $delete,
        private readonly StartFeedbackRecordingProcessing $start,
    ) {}

    /**
     * @param  array<string, string>  $speakers  rótulo => papel (só para «speakers»)
     *
     * @throws FeedbackAiException
     */
    public function execute(FeedbackRecording $recording, User $actor, string $target, array $speakers = []): void
    {
        $from = self::currentStep($recording);

        match ($target) {
            self::STEP_SPEAKERS => $this->toSpeakers($recording, $actor, $speakers, $from),
            self::STEP_UPLOAD => $this->toUpload($recording, $actor, $from),
            self::STEP_PROCESS => $this->toProcess($recording, $actor, $from),
        };
    }

    /** Etapa atual na barra, como a interface mostra. */
    public static function currentStep(FeedbackRecording $recording): string
    {
        return match ($recording->processing_status) {
            FeedbackRecording::STATUS_READY => self::STEP_REVIEW,
            FeedbackRecording::STATUS_PROCESSING, FeedbackRecording::STATUS_FAILED => self::STEP_PROCESS,
            default => $recording->has_speaker_labels && empty($recording->speaker_map) ? self::STEP_SPEAKERS : self::STEP_PROCESS,
        };
    }

    private function toSpeakers(FeedbackRecording $recording, User $actor, array $speakers, string $from): void
    {
        if (! $recording->has_speaker_labels || $from === self::STEP_SPEAKERS) {
            throw ValidationException::withMessages(['step' => 'Não há falantes confirmados para refazer nesta gravação.']);
        }

        DB::transaction(function () use ($recording, $actor, $speakers, $from) {
            /** @var FeedbackRecording $locked */
            $locked = FeedbackRecording::query()->whereKey($recording->id)->lockForUpdate()->firstOrFail();
            $this->assertNotProcessing($locked);

            // Relatório atual vira versão antiga; a transcrição limpa usou os papéis anteriores e será refeita.
            $archived = $locked->reports()->where('is_current', true)->update(['is_current' => false]);
            $locked->forceFill([
                'processing_status' => FeedbackRecording::STATUS_UPLOADED,
                'processing_mode' => null,
                'processing_stage' => null,
                'processing_chunks_done' => 0,
                'processing_chunks_total' => 0,
                'processing_error' => null,
                'processed_at' => null,
                'clean_text' => null,
                'clean_generated_at' => null,
                'clean_edited_by_user_id' => null,
                'clean_edited_at' => null,
            ])->save();

            $this->map->execute($locked, $actor, $speakers);
            $this->audit($locked, $actor, $from, self::STEP_SPEAKERS, ['reports_archived' => $archived]);
        });
    }

    private function toUpload(FeedbackRecording $recording, User $actor, string $from): void
    {
        DB::transaction(function () use ($recording, $actor, $from) {
            /** @var FeedbackRecording $locked */
            $locked = FeedbackRecording::query()->whereKey($recording->id)->lockForUpdate()->firstOrFail();
            $this->assertNotProcessing($locked);

            $this->delete->execute($locked, $actor);
            $this->audit($locked, $actor, $from, self::STEP_UPLOAD);
        });
    }

    /** @throws FeedbackAiException */
    private function toProcess(FeedbackRecording $recording, User $actor, string $from): void
    {
        if ($from !== self::STEP_REVIEW) {
            throw ValidationException::withMessages(['step' => 'Só é possível voltar para «Processar» com o relatório pronto.']);
        }

        $this->start->execute($recording, $actor, StartFeedbackRecordingProcessing::REQUEST_REPORT);
        $this->audit($recording, $actor, $from, self::STEP_PROCESS);
    }

    private function assertNotProcessing(FeedbackRecording $recording): void
    {
        if ($recording->processing_status === FeedbackRecording::STATUS_PROCESSING) {
            throw ValidationException::withMessages(['step' => FeedbackRecordingAccess::REWIND_PROCESSING_MESSAGE]);
        }
    }

    /** @param  array<string, mixed>  $extra */
    private function audit(FeedbackRecording $recording, User $actor, string $from, string $to, array $extra = []): void
    {
        $recording->loadMissing('session');
        FeedbackAuditLog::query()->create([
            'company_id' => $recording->company_id,
            'user_id' => $actor->id,
            'action' => FeedbackAuditLog::ACTION_RECORDING_STEP_REVERTED,
            'subject_type' => FeedbackAuditLog::SUBJECT_RECORDING,
            'subject_id' => $recording->id,
            'subject_label' => $recording->session?->title,
            'details' => ['feedback_session_id' => $recording->feedback_session_id, 'from' => $from, 'to' => $to] + $extra,
        ]);
    }
}
