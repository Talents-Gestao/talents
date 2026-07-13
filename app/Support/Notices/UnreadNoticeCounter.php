<?php

namespace App\Support\Notices;

use App\Enums\CompanyNoticeAudience;
use App\Models\CompanyNotice;
use App\Models\User;

class UnreadNoticeCounter
{
    public function forUser(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        $context = $this->contextFor($user);
        if ($context === null) {
            return 0;
        }

        [$audience, $companyId] = $context;

        return CompanyNotice::query()
            ->where('audience', $audience->value)
            ->when(
                $companyId !== null,
                fn ($query) => $query->where('company_id', $companyId),
                fn ($query) => $query->whereNull('company_id'),
            )
            ->where('published_at', '<=', now())
            ->whereDoesntHave('reads', fn ($query) => $query->where('user_id', $user->id))
            ->count();
    }

    /**
     * Resolve a que conjunto de avisos o utilizador tem acesso no contexto ativo.
     *
     * @return array{0: CompanyNoticeAudience, 1: int|null}|null
     */
    public function contextFor(User $user): ?array
    {
        $companyId = $user->contextCompanyId();
        if ($companyId) {
            return [CompanyNoticeAudience::Company, (int) $companyId];
        }

        if ($user->isSuperAdmin()) {
            return [CompanyNoticeAudience::Talents, null];
        }

        return null;
    }
}
