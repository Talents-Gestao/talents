<?php

declare(strict_types=1);

namespace App\Actions\Feedback;

use App\Models\FeedbackAuditLog;
use App\Models\FeedbackRecording;
use App\Models\FeedbackRecordingReport;
use App\Models\FeedbackSession;
use App\Models\FeedbackSessionAnswer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Exclui um feedback e todas as respostas (exclusão lógica, numa transação) e registra na trilha.
 * Na interface a ação é definitiva; o suporte recupera pelo id gravado em feedback_audit_logs.
 */
class DeleteFeedbackSession
{
    /**
     * @return array{sessions: int, answers: int}
     */
    public function execute(FeedbackSession $session, User $actor): array
    {
        return DB::transaction(function () use ($session, $actor) {
            $session->loadMissing(['employee', 'leader']);

            $answers = FeedbackSessionAnswer::query()->where('feedback_session_id', $session->id)->delete();
            // Gravação (transcrição do Plaud) sai junto; não há arquivo físico guardado.
            $recordings = FeedbackRecording::query()->where('feedback_session_id', $session->id)->delete();
            // Relatórios gerados por IA (derivados da gravação) saem junto.
            FeedbackRecordingReport::query()->where('feedback_session_id', $session->id)->delete();
            $session->delete();

            FeedbackAuditLog::query()->create([
                'company_id' => $session->company_id,
                'user_id' => $actor->id,
                'action' => FeedbackAuditLog::ACTION_SESSION_DELETED,
                'subject_type' => FeedbackAuditLog::SUBJECT_SESSION,
                'subject_id' => $session->id,
                'subject_label' => $session->title,
                'sessions_count' => 1,
                'answers_count' => $answers,
                'details' => [
                    'feedback_template_id' => $session->feedback_template_id,
                    'employee' => $session->employee_name ?? $session->employee?->name,
                    'leader' => $session->leader?->name,
                    'scheduled_at' => $session->scheduled_at?->toIso8601String(),
                    'status' => $session->status?->value,
                    'recordings_count' => $recordings,
                ],
            ]);

            return ['sessions' => 1, 'answers' => $answers];
        });
    }
}
