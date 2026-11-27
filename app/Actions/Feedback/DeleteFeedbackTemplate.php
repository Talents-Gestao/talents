<?php

declare(strict_types=1);

namespace App\Actions\Feedback;

use App\Models\FeedbackAuditLog;
use App\Models\FeedbackSession;
use App\Models\FeedbackTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Exclusão definitiva de um modelo de feedback: só quando nenhum feedback o usa, em nenhuma versão e em nenhuma
 * empresa. Modelo usado não é excluído (arquiva-se) e nenhum feedback é apagado junto com um modelo.
 * Exclusão lógica (deleted_at), com trilha em feedback_audit_logs.
 */
class DeleteFeedbackTemplate
{
    public const IN_USE_MESSAGE = 'Este modelo já foi usado em feedbacks e não pode ser excluído. Arquive-o para que não apareça em novos feedbacks.';

    /** Feedbacks (não excluídos) feitos com o modelo, em qualquer versão e empresa. */
    public function usage(FeedbackTemplate $template): int
    {
        return FeedbackSession::query()->where('feedback_template_id', $template->id)->count();
    }

    /**
     * @throws ValidationException quando o modelo já foi usado
     */
    public function execute(FeedbackTemplate $template, User $actor): void
    {
        DB::transaction(function () use ($template, $actor) {
            $locked = FeedbackTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();

            if (FeedbackSession::query()->where('feedback_template_id', $locked->id)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['template' => self::IN_USE_MESSAGE]);
            }

            $locked->delete();

            FeedbackAuditLog::query()->create([
                'company_id' => $locked->company_id,
                'user_id' => $actor->id,
                'action' => FeedbackAuditLog::ACTION_TEMPLATE_DELETED,
                'subject_type' => FeedbackAuditLog::SUBJECT_TEMPLATE,
                'subject_id' => $locked->id,
                'subject_label' => $locked->title,
                'sessions_count' => 0,
                'answers_count' => 0,
            ]);
        });
    }
}
