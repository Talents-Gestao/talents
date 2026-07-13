<?php

declare(strict_types=1);

namespace App\Actions\Notices;

use App\Enums\CompanyNoticeAudience;
use App\Enums\CompanyNoticeEventKind;
use App\Models\LandingInterestSubmission;

/**
 * Aviso interno da Talents quando chega um novo lead da landing page.
 */
class PublishLeadNotice
{
    public function __construct(
        private readonly PublishCompanyNotice $publish,
    ) {}

    public function received(LandingInterestSubmission $submission): void
    {
        $company = $submission->company ? " ({$submission->company})" : '';

        $this->publish->handle(
            companyId: null,
            title: 'Novo lead recebido',
            body: "{$submission->name}{$company} demonstrou interesse. Contacto: "
                .$submission->email.($submission->phone ? " · {$submission->phone}" : '').'.',
            audience: CompanyNoticeAudience::Talents,
            sourceType: 'landing_interest_submission',
            sourceId: (int) $submission->id,
            eventKind: CompanyNoticeEventKind::LeadReceived,
            dedupeWithinMinutes: 5,
        );
    }
}
