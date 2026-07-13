<?php

declare(strict_types=1);

namespace App\Support\Ferias;

use App\Enums\PermissionModule;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class FeriasCompanyContext
{
    public const SESSION_KEY = 'ferias_company_id';

    public function needsCompanySelection(Request $request): bool
    {
        $user = $request->user();

        if (! $user?->isSuperAdmin() || ! $request->routeIs('admin.ferias.*')) {
            return false;
        }

        if ($user->contextCompany()?->hasFeriasEnabled()) {
            return false;
        }

        $sessionCompanyId = $request->session()->get(self::SESSION_KEY);

        if (! $sessionCompanyId) {
            return true;
        }

        $company = Company::query()->find($sessionCompanyId);

        return ! $company || ! $company->hasFeriasEnabled();
    }

    public function resolve(Request $request): Company
    {
        $user = $request->user();
        abort_unless($user, 403);

        $workspaceCompany = $user->contextCompany();

        if ($workspaceCompany && $workspaceCompany->hasModuleEnabled(PermissionModule::Ferias)) {
            return $workspaceCompany;
        }

        if ($user->isSuperAdmin() && $request->routeIs('admin.ferias.*')) {
            $sessionCompanyId = $request->session()->get(self::SESSION_KEY);
            abort_unless($sessionCompanyId, 403, 'Selecione uma empresa para continuar.');

            $company = Company::query()->findOrFail($sessionCompanyId);
            abort_unless($company->hasFeriasEnabled(), 403);

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
            ->get(['id', 'name', 'ferias_access'])
            ->filter(fn (Company $company) => $company->hasFeriasEnabled())
            ->map(fn (Company $company) => $company->only(['id', 'name']))
            ->values();
    }

    public function isAdminContext(Request $request): bool
    {
        return $request->routeIs('admin.ferias.*');
    }
}
