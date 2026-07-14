<?php

declare(strict_types=1);

namespace App\Support\Complaints;

use App\Enums\PermissionModule;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class ComplaintCompanyContext
{
    public const SESSION_KEY = 'complaints_company_id';

    public function needsCompanySelection(Request $request): bool
    {
        $user = $request->user();

        if (! $user?->isSuperAdmin() || ! $request->routeIs('admin.complaints.*')) {
            return false;
        }

        if ($user->contextCompany()?->hasComplaintsEnabled()) {
            return false;
        }

        $sessionCompanyId = $request->session()->get(self::SESSION_KEY);

        if (! $sessionCompanyId) {
            return true;
        }

        $company = Company::query()->find($sessionCompanyId);

        return ! $company || ! $company->hasComplaintsEnabled();
    }

    public function resolve(Request $request): Company
    {
        $user = $request->user();
        abort_unless($user, 403);

        $workspaceCompany = $user->contextCompany();

        if ($workspaceCompany && $workspaceCompany->hasModuleEnabled(PermissionModule::Denuncias)) {
            return $workspaceCompany;
        }

        if ($user->isSuperAdmin() && $request->routeIs('admin.complaints.*')) {
            $sessionCompanyId = $request->session()->get(self::SESSION_KEY);
            abort_unless($sessionCompanyId, 403, 'Selecione uma empresa para continuar.');

            $company = Company::query()->findOrFail($sessionCompanyId);
            abort_unless($company->hasComplaintsEnabled(), 403);

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
            ->get(['id', 'name', 'denuncias_access'])
            ->filter(fn (Company $company) => $company->hasComplaintsEnabled())
            ->map(fn (Company $company) => $company->only(['id', 'name']))
            ->values();
    }

    public function isAdminContext(Request $request): bool
    {
        return $request->routeIs('admin.complaints.*');
    }
}
