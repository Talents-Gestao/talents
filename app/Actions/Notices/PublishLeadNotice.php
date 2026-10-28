<?php

declare(strict_types=1);

namespace App\Actions\Notices;

use App\Enums\CompanyNoticeAudience;
use App\Enums\CompanyNoticeEventKind;
use App\Models\LandingInterestSubmission;
use App\Support\Notices\UnreadNoticeCounter;

/**
 * Aviso interno da Talents quando chega um novo lead da landing page.
 */
class PublishLeadNotice
{
    public function __construct(
        private readonly PublishCompanyNotice $publish,
        private readonly UnreadNoticeCounter $unreadNoticeCounter,
    ) {}

    public function received(LandingInterestSubmission $submission): void
    {
        $submission->loadMissing(['creator:id,name', 'assignee:id,name']);

        $company = $submission->company ? " ({$submission->company})" : '';
        $contact = $submission->email.($submission->phone ? " · {$submission->phone}" : '');
        $creatorName = $submission->creator?->name;
        $assigneeName = $submission->assignee?->name;
        $assignedToOther = $submission->assigned_to !== null
            && $submission->created_by !== null
            && (int) $submission->assigned_to !== (int) $submission->created_by;

        if ($assignedToOther && $creatorName && $assigneeName) {
            $title = "Lead cadastrado para {$assigneeName}";
            $body = "{$creatorName} cadastrou o lead «{$submission->name}»{$company} para {$assigneeName}. Contato: {$contact}.";
        } elseif ($creatorName && $assigneeName && (int) $submission->assigned_to === (int) $submission->created_by) {
            $title = 'Novo lead cadastrado';
            $body = "{$creatorName} cadastrou o lead «{$submission->name}»{$company}. Contato: {$contact}.";
        } elseif ($creatorName) {
            $title = 'Novo lead cadastrado';
            $body = "{$creatorName} cadastrou o lead «{$submission->name}»{$company}. Contato: {$contact}.";
        } else {
            $title = 'Novo lead recebido';
            $body = "{$submission->name}{$company} demonstrou interesse. Contato: {$contact}.";
        }

        $this->publish->handle(
            companyId: null,
            title: $title,
            body: $body,
            audience: CompanyNoticeAudience::Talents,
            actor: $submission->creator,
            sourceType: 'landing_interest_submission',
            sourceId: (int) $submission->id,
            eventKind: CompanyNoticeEventKind::LeadReceived,
            dedupeWithinMinutes: 5,
            targetUserId: $assignedToOther ? (int) $submission->assigned_to : null,
        );

        if ($assignedToOther && $submission->assigned_to) {
            $this->unreadNoticeCounter->forgetForUserId((int) $submission->assigned_to);
        }
    }
}
