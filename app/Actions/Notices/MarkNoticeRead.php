<?php

namespace App\Actions\Notices;

use App\Enums\CompanyNoticeAudience;
use App\Models\CompanyNotice;
use App\Models\CompanyNoticeRead;
use App\Models\User;
use App\Support\Notices\UnreadNoticeCounter;
use Illuminate\Support\Collection;

class MarkNoticeRead
{
    public function __construct(
        private readonly UnreadNoticeCounter $unreadNoticeCounter,
    ) {}

    public function handle(CompanyNotice $notice, User $user): void
    {
        CompanyNoticeRead::query()->updateOrCreate(
            [
                'company_notice_id' => $notice->id,
                'user_id' => $user->id,
            ],
            ['read_at' => now()],
        );

        $this->unreadNoticeCounter->forget($user);
    }

    public function markAllForUser(User $user, int $companyId): int
    {
        return $this->markAllForContext($user, CompanyNoticeAudience::Company, $companyId);
    }

    /**
     * Marca como lidos todos os avisos visíveis no contexto ativo do utilizador
     * (admin Talents = todos; cliente = só a empresa).
     */
    public function markAllVisibleForUser(User $user, UnreadNoticeCounter $unreadNoticeCounter): int
    {
        $query = $unreadNoticeCounter->visibleNoticesQuery($user);
        if ($query === null) {
            return 0;
        }

        $noticeIds = $query
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id))
            ->pluck('id');

        return $this->markIdsAsRead($user, $noticeIds);
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

        return $this->markIdsAsRead($user, $noticeIds);
    }

    /**
     * @param  Collection<int, int|string>  $noticeIds
     */
    private function markIdsAsRead(User $user, Collection $noticeIds): int
    {
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

        $this->unreadNoticeCounter->forget($user);

        return $noticeIds->count();
    }
}
