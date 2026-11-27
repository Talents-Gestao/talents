<?php

declare(strict_types=1);

namespace App\Actions\Notices;

use App\Enums\CompanyNoticeAudience;
use App\Enums\CompanyNoticeEventKind;
use App\Enums\FollowupAudience;
use App\Models\HiringFollowupResponse;

/**
 * Aviso interno da Talents quando alguém pede apoio na pesquisa pós-contratação.
 * Nunca leva o comentário nem o texto do pedido: só quem, onde e em que marco. Não vai à empresa.
 */
class PublishHiringFollowupNotice
{
    public function __construct(
        private readonly PublishCompanyNotice $publish,
    ) {}

    public function supportRequested(HiringFollowupResponse $response): void
    {
        $response->loadMissing(['invitation.milestone.cycle.employee:id,name', 'invitation.milestone.cycle.company:id,name']);
        $invitation = $response->invitation;
        $milestone = $invitation->milestone;
        $cycle = $milestone->cycle;

        $who = $invitation->audience === FollowupAudience::Collaborator
            ? 'O colaborador '.$cycle->employee?->name
            : 'O gestor de '.$cycle->employee?->name;

        $this->publish->handle(
            companyId: null,
            title: 'Pedido de apoio no pós-contratação',
            body: $who.' ('.$cycle->company?->name.') pediu apoio no acompanhamento '.$milestone->moment->label().'. Abra o ciclo para ver a resposta.',
            audience: CompanyNoticeAudience::Talents,
            sourceType: 'employment_cycle',
            sourceId: (int) $cycle->id,
            eventKind: CompanyNoticeEventKind::HiringFollowupSupportRequested,
            dedupeWithinMinutes: 5,
        );
    }
}
