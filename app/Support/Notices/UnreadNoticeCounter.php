<?php

declare(strict_types=1);

namespace App\Support\Notices;

use App\Enums\CompanyNoticeAudience;
use App\Enums\CompanyNoticeEventKind;
use App\Models\CompanyNotice;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
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

        return (int) Cache::remember(
            $this->cacheKey($user, $audience, $companyId),
            now()->addSeconds(20),
            function () use ($user, $audience, $companyId): int {
                $query = CompanyNotice::query()
                    ->where('published_at', '<=', now());

                if ($audience !== CompanyNoticeAudience::Talents) {
                    $query
                        ->where('audience', $audience->value)
                        ->where('company_id', $companyId);
                }

                return (int) $query
                    ->whereNotExists(function ($sub) use ($user): void {
                        $sub->selectRaw('1')
                            ->from('company_notice_reads')
                            ->whereColumn('company_notice_reads.company_notice_id', 'company_notices.id')
                            ->where('company_notice_reads.user_id', $user->id);
                    })
                    ->count();
            },
        );
    }

    /**
     * Leads atribuídos ao utilizador atual e ainda não lidos.
     * Usado pelo badge flutuante (não pelo sino, que continua a contar todos os avisos).
     */
    public function unreadAssignedLeadsForUser(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        $context = $this->contextFor($user);
        if ($context === null) {
            return 0;
        }

        [$audience] = $context;
        if ($audience !== CompanyNoticeAudience::Talents) {
            return 0;
        }

        return (int) Cache::remember(
            $this->assignedLeadsCacheKey((int) $user->id),
            now()->addSeconds(20),
            function () use ($user): int {
                return (int) CompanyNotice::query()
                    ->where('published_at', '<=', now())
                    ->where('event_kind', CompanyNoticeEventKind::LeadReceived)
                    ->where('target_user_id', $user->id)
                    ->whereNotExists(function ($sub) use ($user): void {
                        $sub->selectRaw('1')
                            ->from('company_notice_reads')
                            ->whereColumn('company_notice_reads.company_notice_id', 'company_notices.id')
                            ->where('company_notice_reads.user_id', $user->id);
                    })
                    ->count();
            },
        );
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
     * Lista do sino: não lidos primeiro, depois os mais recentes.
     *
     * @return array{notices: Collection<int, CompanyNotice>, has_more: bool, page: int}|null
     */
    public function paginateForBell(User $user, int $page = 1, int $perPage = 50): ?array
    {
        $query = $this->visibleNoticesQuery($user);
        if ($query === null) {
            return null;
        }

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $rows = $query
            ->with([
                'company:id,name',
                'reads' => fn ($q) => $q->where('user_id', $user->id),
            ])
            ->orderByRaw(
                'CASE WHEN EXISTS (
                    SELECT 1 FROM company_notice_reads
                    WHERE company_notice_reads.company_notice_id = company_notices.id
                      AND company_notice_reads.user_id = ?
                ) THEN 1 ELSE 0 END',
                [$user->id],
            )
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->skip(($page - 1) * $perPage)
            ->take($perPage + 1)
            ->get();

        $hasMore = $rows->count() > $perPage;

        return [
            'notices' => $rows->take($perPage)->values(),
            'has_more' => $hasMore,
            'page' => $page,
        ];
    }

    /**
     * Resolve a que conjunto de avisos o usuário tem acesso no contexto ativo.
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

    public function forget(User $user): void
    {
        $this->forgetForUserId((int) $user->id);

        $context = $this->contextFor($user);
        if ($context === null) {
            return;
        }

        [$audience, $companyId] = $context;
        Cache::forget($this->cacheKey($user, $audience, $companyId));
    }

    /**
     * Invalida o cache do badge de leads atribuídos sem depender do workspace da sessão.
     * Usado ao publicar um lead para outro comercial.
     */
    public function forgetForUserId(int $userId): void
    {
        Cache::forget($this->assignedLeadsCacheKey($userId));
        Cache::forget(sprintf(
            'nav.unread_notices.%d.%s.all',
            $userId,
            CompanyNoticeAudience::Talents->value,
        ));
    }

    private function cacheKey(User $user, CompanyNoticeAudience $audience, ?int $companyId): string
    {
        return sprintf(
            'nav.unread_notices.%d.%s.%s',
            $user->id,
            $audience->value,
            $companyId ?? 'all',
        );
    }

    private function assignedLeadsCacheKey(int $userId): string
    {
        return sprintf('nav.unread_assigned_leads.%d', $userId);
    }
}
