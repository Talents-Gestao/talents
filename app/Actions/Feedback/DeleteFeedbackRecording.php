<?php

declare(strict_types=1);

namespace App\Actions\Feedback;

use App\Models\FeedbackAuditLog;
use App\Models\FeedbackRecording;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Exclui a gravação de um feedback (exclusão lógica, como o resto de Feedbacks) e registra na trilha.
 * Não há arquivo físico a apagar: o arquivo enviado é descartado logo após a extração do texto.
 */
class DeleteFeedbackRecording
{
    public function execute(FeedbackRecording $recording, User $actor): void
    {
        DB::transaction(function () use ($recording, $actor) {
            $recording->loadMissing('session');
            $recording->delete();
            // Transcrição limpa vai junto com a linha; os relatórios gerados a partir dela também.
            $reports = $recording->reports()->delete();

            FeedbackAuditLog::query()->create([
                'company_id' => $recording->company_id,
                'user_id' => $actor->id,
                'action' => FeedbackAuditLog::ACTION_RECORDING_DELETED,
                'subject_type' => FeedbackAuditLog::SUBJECT_RECORDING,
                'subject_id' => $recording->id,
                'subject_label' => $recording->session?->title,
                'details' => [
                    'feedback_session_id' => $recording->feedback_session_id,
                    'sha256' => $recording->file_sha256,
                    'reports_count' => $reports,
                ],
            ]);
        });
    }
}
