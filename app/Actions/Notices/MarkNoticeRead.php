<?php

namespace App\Actions\Notices;

use App\Enums\CompanyNoticeAudience;
use App\Models\CompanyNotice;
use App\Models\CompanyNoticeRead;
use App\Models\User;

class MarkNoticeRead
{
    public function handle(CompanyNotice $notice, User $user): void
    {
        CompanyNoticeRead::query()->updateOrCreate(
            [
                'company_notice_id' => $notice->id,
                'user_id' => $user->id,
            ],
            ['read_at' => now()],
        );
    }

    public function markAllForUser(User $user, int $companyId): int
    {
        return $this->markAllForContext($user, CompanyNoticeAudience::Company, $companyId);
    }

    public function markAllForContext(User $user, CompanyNoticeAudience $audience, ?int $companyId): int
    {
        $noticeIds = CompanyNotice::query()
            ->where('audience', $audience->value)
            ->when(
                $companyId !== null,
                fn ($query) => $query->where('company_id', $companyId),
                fn ($query) => $query->whereNull('company_id'),
            )
            ->where('published_at', '<=', now())
            ->whereDoesntHave('reads', fn ($query) => $query->where('user_id', $user->id))
            ->pluck('id');

        $now = now();
        foreach ($noticeIds as $noticeId) {
            CompanyNoticeRead::query()->updateOrCreate(
                [
                    'company_notice_id' => $noticeId,
                    'user_id' => $user->id,
                ],
                ['read_at' => $now],
            );
        }

        return $noticeIds->count();
    }
}
