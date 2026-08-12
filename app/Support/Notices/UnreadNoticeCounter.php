<?php

declare(strict_types=1);

namespace App\Support\Notices;

use App\Enums\CompanyNoticeAudience;
use App\Models\CompanyNotice;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

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
        $cacheKey = sprintf(
            'nav.unread_notices.%d.%s.%s',
            $user->id,
            $audience->value,
            $companyId ?? 'all',
        );

        return (int) Cache::remember($cacheKey, now()->addSeconds(20), function () use ($user, $audience, $companyId) {
            $query = CompanyNotice::query()
                ->where('published_at', '<=', now());

            if ($audience !== CompanyNoticeAudience::Talents) {
                $query
                    ->where('audience', $audience->value)
                    ->where('company_id', $companyId);
            }

            return $query
                ->whereNotExists(function ($sub) use ($user): void {
                    $sub->selectRaw('1')
                        ->from('company_notice_reads')
                        ->whereColumn('company_notice_reads.company_notice_id', 'company_notices.id')
                        ->where('company_notice_reads.user_id', $user->id);
                })
                ->count();
        });
    }

    /**
     * Avisos visíveis no contexto ativo (sino / contador / marcar todos).
     *
     * Workspace Talents (super_admin): todas as CompanyNotice.
     * Workspace empresa: só audience=company da empresa ativa.
     *
     * @return Builder<CompanyNotice>|null
     */
    public function visibleNoticesQuery(User $user): ?Builder
    {
        $context = $this->contextFor($user);
        if ($context === null) {
            return null;
        }

        [$audience, $companyId] = $context;

        $query = CompanyNotice::query()
            ->where('published_at', '<=', now());

        // Admin Talents: talents + company de qualquer empresa.
        if ($audience === CompanyNoticeAudience::Talents) {
            return $query;
        }

        return $query
            ->where('audience', $audience->value)
            ->where('company_id', $companyId);
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
