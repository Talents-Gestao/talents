<?php

declare(strict_types=1);

namespace App\Actions\Notices;

use App\Enums\CompanyNoticeAudience;
use App\Enums\CompanyNoticeEventKind;
use App\Models\Company;
use App\Models\FeedbackSession;
use App\Models\User;
use App\Support\Feedback\Ai\FeedbackAiMonthlyLimit;

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

    /** Aviso à Talents: a empresa atingiu o limite mensal de IA de Feedbacks (um aviso por empresa no mês). */
    public function aiLimitReached(Company $company): void
    {
        $this->publish->handle(
            companyId: (int) $company->id,
            title: 'Limite de IA de Feedbacks atingido',
            body: "A empresa {$company->name} atingiu o limite mensal de IA de Feedbacks. Novos processamentos ficam bloqueados até o próximo mês ou até o limite ser ampliado em Configurações.",
            audience: CompanyNoticeAudience::Talents,
            sourceType: 'company',
            sourceId: (int) $company->id,
            eventKind: CompanyNoticeEventKind::FeedbackAiLimitReached,
            dedupeWithinMinutes: max(1, (int) now()->diffInMinutes(FeedbackAiMonthlyLimit::monthStart(), true)),
        );
    }
}
