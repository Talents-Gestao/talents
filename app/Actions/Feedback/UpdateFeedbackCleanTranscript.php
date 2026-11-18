<?php

declare(strict_types=1);

namespace App\Actions\Feedback;

use App\Models\FeedbackAuditLog;
use App\Models\FeedbackRecording;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Edição humana da transcrição limpa (o original nunca muda). Cada salvamento sobe a revisão e grava autor e data;
 * a trilha registra a revisão e o tamanho, nunca o texto. Recusa edição sobre uma revisão desatualizada.
 */
class UpdateFeedbackCleanTranscript
{
    public function execute(FeedbackRecording $recording, User $actor, string $text, int $expectedRevision): FeedbackRecording
    {
        return DB::transaction(function () use ($recording, $actor, $text, $expectedRevision) {
            /** @var FeedbackRecording $locked */
            $locked = FeedbackRecording::query()->whereKey($recording->id)->lockForUpdate()->firstOrFail();

            if ($locked->processing_status === FeedbackRecording::STATUS_PROCESSING || ! $locked->hasCleanText()) {
                throw ValidationException::withMessages(['clean_text' => 'A transcrição limpa não pode ser editada agora.']);
            }

            if ((int) $locked->clean_revision !== $expectedRevision) {
                throw ValidationException::withMessages(['clean_text' => 'A transcrição limpa foi alterada por outra pessoa. Recarregue a página e edite de novo.']);
            }

            $locked->forceFill([
                'clean_text' => $text,
                'clean_revision' => $expectedRevision + 1,
                'clean_edited_by_user_id' => $actor->id,
                'clean_edited_at' => now(),
            ])->save();

            $locked->loadMissing('session');
            FeedbackAuditLog::query()->create([
                'company_id' => $locked->company_id,
                'user_id' => $actor->id,
                'action' => FeedbackAuditLog::ACTION_RECORDING_CLEAN_EDITED,
                'subject_type' => FeedbackAuditLog::SUBJECT_RECORDING,
                'subject_id' => $locked->id,
                'subject_label' => $locked->session?->title,
                'details' => [
                    'feedback_session_id' => $locked->feedback_session_id,
                    'revision' => $locked->clean_revision,
                    'chars' => mb_strlen($text),
                ],
            ]);

            return $locked;
        });
    }
}
