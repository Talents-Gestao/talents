<?php

declare(strict_types=1);

namespace App\Support\Feedback;

use App\Enums\PermissionModule;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class FeedbackCompanyContext
{
    public const SESSION_KEY = 'feedback_company_id';

    public function needsCompanySelection(Request $request): bool
    {
        $user = $request->user();

        if (! $user?->isSuperAdmin() || ! $request->routeIs('admin.feedbacks.*')) {
            return false;
        }

        if ($user->contextCompany()?->hasFeedbacksEnabled()) {
            return false;
        }

        $sessionCompanyId = $request->session()->get(self::SESSION_KEY);

        if (! $sessionCompanyId) {
            return true;
        }

        $company = Company::query()->find($sessionCompanyId);

        return ! $company || ! $company->hasFeedbacksEnabled();
    }

    public function resolve(Request $request): Company
    {
        $user = $request->user();
        abort_unless($user, 403);

        $workspaceCompany = $user->contextCompany();

        if ($workspaceCompany && $workspaceCompany->hasModuleEnabled(PermissionModule::Feedbacks)) {
            return $workspaceCompany;
        }

        if ($user->isSuperAdmin() && $request->routeIs('admin.feedbacks.*')) {
            $sessionCompanyId = $request->session()->get(self::SESSION_KEY);
            abort_unless($sessionCompanyId, 403, 'Selecione uma empresa para continuar.');

            $company = Company::query()->findOrFail($sessionCompanyId);
            abort_unless($company->hasFeedbacksEnabled(), 403);

            return $company;
        }

        abort(403);
    }

    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    public function availableCompanies(): Collection
    {
        return Company::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'feedbacks_access'])
            ->filter(fn (Company $company) => $company->hasFeedbacksEnabled())
            ->map(fn (Company $company) => $company->only(['id', 'name']))
            ->values();
    }

    public function activeCompanySummary(Request $request): ?array
    {
        if ($this->needsCompanySelection($request)) {
            return null;
        }

        try {
            return $this->resolve($request)->only(['id', 'name']);
        } catch (\Throwable) {
            return null;
        }
    }

    public function isAdminContext(Request $request): bool
    {
        return $request->routeIs('admin.feedbacks.*');
    }

    public function actsAsCompanyAdmin(User $user): bool
    {
        return $user->isCompanyAdmin() || $user->isSuperAdmin();
    }
}
