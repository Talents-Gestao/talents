<?php

namespace App\Support\Notices;

use App\Models\CompanyNotice;
use App\Models\User;

class UnreadNoticeCounter
{
    public function forUser(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        $companyId = $user->contextCompanyId();
        if (! $companyId) {
            return 0;
        }

        return CompanyNotice::query()
            ->where('company_id', $companyId)
            ->where('published_at', '<=', now())
            ->whereDoesntHave('reads', fn ($query) => $query->where('user_id', $user->id))
            ->count();
    }
}
