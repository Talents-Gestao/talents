<?php

namespace App\Actions\Notices;

use App\Enums\CompanyNoticeEventKind;
use App\Models\CompanyNotice;
use App\Models\User;
use Carbon\Carbon;

class PublishCompanyNotice
{
    public function handle(
        int $companyId,
        string $title,
        string $body,
        ?User $actor = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?CompanyNoticeEventKind $eventKind = null,
        ?Carbon $publishedAt = null,
    ): CompanyNotice {
        return CompanyNotice::query()->create([
            'company_id' => $companyId,
            'title' => $title,
            'body' => $body,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'event_kind' => $eventKind?->value,
            'published_at' => $publishedAt ?? now(),
            'created_by_user_id' => $actor?->id,
        ]);
    }
}
