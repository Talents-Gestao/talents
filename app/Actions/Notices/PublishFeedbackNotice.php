<?php

declare(strict_types=1);

namespace App\Actions\Notices;

use App\Enums\CompanyNoticeAudience;
use App\Enums\CompanyNoticeEventKind;
use App\Models\FeedbackSession;
use App\Models\User;

/**
 * Avisos por empresa para o ciclo de feedbacks internos.
 */
class PublishFeedbackNotice
{
    public function __construct(
        private readonly PublishCompanyNotice $publish,
    ) {}

    public function awaitingSignature(FeedbackSession $session, ?User $actor = null): void
    {
        if (! $session->company_id) {
            return;
        }

        $session->loadMissing('employee');
        $who = $session->employee?->name ?? 'colaborador';

        $this->publish->handle(
            companyId: (int) $session->company_id,
            title: 'Feedback enviado para assinatura',
            body: "O feedback «{$session->title}» de {$who} foi enviado para assinatura do colaborador e do líder.",
            audience: CompanyNoticeAudience::Company,
            actor: $actor,
            sourceType: 'feedback_session',
            sourceId: (int) $session->id,
            eventKind: CompanyNoticeEventKind::FeedbackAwaitingSignature,
            dedupeWithinMinutes: 5,
        );
    }

    public function completed(FeedbackSession $session): void
    {
        if (! $session->company_id) {
            return;
        }

        $session->loadMissing('employee');
        $who = $session->employee?->name ?? 'colaborador';

        $this->publish->handle(
            companyId: (int) $session->company_id,
            title: 'Feedback concluído',
            body: "O feedback «{$session->title}» de {$who} foi assinado por todas as partes e está concluído.",
            audience: CompanyNoticeAudience::Company,
            sourceType: 'feedback_session',
            sourceId: (int) $session->id,
            eventKind: CompanyNoticeEventKind::FeedbackCompleted,
            dedupeWithinMinutes: 5,
        );
    }
}
