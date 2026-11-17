<?php

declare(strict_types=1);

namespace App\Actions\Feedback;

use App\Models\FeedbackAuditLog;
use App\Models\FeedbackSession;
use App\Models\FeedbackSessionAnswer;
use App\Models\FeedbackTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Exclui um modelo e, junto, todos os feedbacks feitos com ele e as respostas: tudo ou nada, numa transação,
 * com exclusão lógica (o suporte recupera pelos ids da trilha). A contagem confirmada na tela precisa bater
 * com a atual; se mudou entre abrir o alerta e confirmar, a exclusão é recusada.
 */
class DeleteFeedbackTemplate
{
    /**
     * Impacto atual, calculado no servidor para o alerta.
     *
     * @return array{sessions: int, answers: int}
     */
    public function impact(FeedbackTemplate $template): array
    {
        $sessionIds = FeedbackSession::query()->where('feedback_template_id', $template->id)->pluck('id');

        return [
            'sessions' => $sessionIds->count(),
            'answers' => $sessionIds->isEmpty()
                ? 0
                : FeedbackSessionAnswer::query()->whereIn('feedback_session_id', $sessionIds)->count(),
        ];
    }

    /**
     * @return array{sessions: int, answers: int}
     *
     * @throws ValidationException quando a contagem confirmada não bate com a atual
     */
    public function execute(FeedbackTemplate $template, User $actor, int $confirmedSessions): array
    {
        return DB::transaction(function () use ($template, $actor, $confirmedSessions) {
            $locked = FeedbackTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();

            $sessionIds = FeedbackSession::query()
                ->where('feedback_template_id', $locked->id)
                ->lockForUpdate()
                ->pluck('id');

            if ($sessionIds->count() !== $confirmedSessions) {
                throw ValidationException::withMessages([
                    'sessions_count' => sprintf(
                        'O número de feedbacks deste modelo mudou desde que você abriu o alerta (agora %s). Confira o impacto e confirme de novo.',
                        $sessionIds->count() === 1 ? 'é 1' : 'são '.$sessionIds->count(),
                    ),
                ]);
            }

            $answers = $sessionIds->isEmpty()
                ? 0
                : FeedbackSessionAnswer::query()->whereIn('feedback_session_id', $sessionIds)->delete();

            if ($sessionIds->isNotEmpty()) {
                FeedbackSession::query()->whereIn('id', $sessionIds)->delete();
            }

            $locked->delete();

            FeedbackAuditLog::query()->create([
                'company_id' => $locked->company_id,
                'user_id' => $actor->id,
                'action' => FeedbackAuditLog::ACTION_TEMPLATE_DELETED,
                'subject_type' => FeedbackAuditLog::SUBJECT_TEMPLATE,
                'subject_id' => $locked->id,
                'subject_label' => $locked->title,
                'sessions_count' => $sessionIds->count(),
                'answers_count' => $answers,
                'details' => ['feedback_session_ids' => $sessionIds->values()->all()],
            ]);

            return ['sessions' => $sessionIds->count(), 'answers' => $answers];
        });
    }
}
