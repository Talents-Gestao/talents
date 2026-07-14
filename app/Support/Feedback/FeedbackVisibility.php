<?php

declare(strict_types=1);

namespace App\Support\Feedback;

use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\FeedbackSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class FeedbackVisibility
{
    public const AUDIENCE_LEADER_SELF = 'leader_self';

    public static function userCanAccessModule(User $user, Company $company): bool
    {
        if (! $company->hasFeedbacksEnabled()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isCompanyAdmin() || $user->isCompanyUser();
    }

    public static function actsAsCompanyAdmin(User $user): bool
    {
        return $user->isCompanyAdmin() || $user->isSuperAdmin();
    }

    /**
     * Secção «Perguntas do líder para o líder» — visível apenas ao perfil admin.
     */
    public static function canViewLeaderSelfSections(User $user): bool
    {
        return self::actsAsCompanyAdmin($user);
    }

    /**
     * @param  Builder<FeedbackSession>  $query
     * @return Builder<FeedbackSession>
     */
    public static function scopeSessions(Builder $query, User $user): Builder
    {
        if (self::actsAsCompanyAdmin($user)) {
            return $query;
        }

        return $query->where('leader_user_id', $user->id);
    }

    /**
     * @param  Builder<CompanyEmployee>  $query
     * @return Builder<CompanyEmployee>
     */
    public static function scopeEmployees(Builder $query, User $user): Builder
    {
        if (self::actsAsCompanyAdmin($user)) {
            return $query;
        }

        return $query->where('leader_user_id', $user->id);
    }

    public static function authorizeSession(User $user, FeedbackSession $session): void
    {
        if ($user->isSuperAdmin()) {
            $company = app(FeedbackCompanyContext::class)->resolve(request());
            abort_unless($session->company_id === $company->id, 403);
            abort_unless($session->company->hasFeedbacksEnabled(), 403);

            return;
        }

        abort_unless($session->company_id === $user->contextCompany()?->id, 403);

        if ($user->isCompanyAdmin()) {
            return;
        }

        abort_unless((int) $session->leader_user_id === (int) $user->id, 403);
    }

    public static function authorizeEmployee(User $user, CompanyEmployee $employee): void
    {
        if ($user->isSuperAdmin()) {
            $company = app(FeedbackCompanyContext::class)->resolve(request());
            abort_unless($employee->company_id === $company->id, 403);
            abort_unless($employee->company->hasFeedbacksEnabled(), 403);

            return;
        }

        abort_unless($employee->company_id === $user->contextCompany()?->id, 403);

        if ($user->isCompanyAdmin()) {
            return;
        }

        abort_unless((int) $employee->leader_user_id === (int) $user->id, 403);
    }
}
