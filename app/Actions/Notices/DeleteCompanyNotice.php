<?php

declare(strict_types=1);

namespace App\Actions\Notices;

use App\Models\CompanyNotice;
use App\Models\User;
use App\Support\Notices\UnreadNoticeCounter;

class DeleteCompanyNotice
{
    public function __construct(
        private readonly UnreadNoticeCounter $unreadNoticeCounter,
    ) {}

    public function handle(CompanyNotice $notice, User $user): void
    {
        $notice->delete();
        $this->unreadNoticeCounter->forget($user);
    }

    /**
     * Remove todos os avisos visíveis no contexto ativo (admin = todos; cliente = empresa).
     */
    public function deleteAllVisibleForUser(User $user): int
    {
        $query = $this->unreadNoticeCounter->visibleNoticesQuery($user);
        if ($query === null) {
            return 0;
        }

        $ids = $query->pluck('id');
        if ($ids->isEmpty()) {
            return 0;
        }

        CompanyNotice::query()->whereIn('id', $ids)->delete();
        $this->unreadNoticeCounter->forget($user);

        return $ids->count();
    }
}
