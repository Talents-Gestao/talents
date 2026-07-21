<?php

declare(strict_types=1);

namespace App\Support\Offboarding;

use App\Enums\PermissionModule;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class DesligamentoCompanyContext
{
    public const SESSION_KEY = 'desligamento_company_id';

    public function needsCompanySelection(Request $request): bool
    {
        $user = $request->user();

        if (! $user?->isSuperAdmin() || ! $this->isAdminContext($request)) {
            return false;
        }

        if ($user->contextCompany()?->hasDesligamentoEnabled()) {
            return false;
        }

        $sessionCompanyId = $request->session()->get(self::SESSION_KEY);

        if (! $sessionCompanyId) {
            return true;
        }

        $company = Company::query()->find($sessionCompanyId);

        return ! $company || ! $company->hasDesligamentoEnabled();
    }

    public function resolve(Request $request): Company
    {
        $user = $request->user();
        abort_unless($user, 403);

        $workspaceCompany = $user->contextCompany();

        if ($workspaceCompany && $workspaceCompany->hasModuleEnabled(PermissionModule::Desligamento)) {
            return $workspaceCompany;
        }

        if ($user->isSuperAdmin() && $this->isAdminContext($request)) {
            $sessionCompanyId = $request->session()->get(self::SESSION_KEY);
            abort_unless($sessionCompanyId, 403, 'Selecione uma empresa para continuar.');

            $company = Company::query()->findOrFail($sessionCompanyId);
            abort_unless($company->hasDesligamentoEnabled(), 403);

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
            ->get(['id', 'name', 'desligamento_access'])
            ->filter(fn (Company $company) => $company->hasDesligamentoEnabled())
            ->map(fn (Company $company) => $company->only(['id', 'name']))
            ->values();
    }

    public function isAdminContext(Request $request): bool
    {
        return $request->routeIs('admin.desligamento.*')
            || $request->routeIs('admin.survey-templates.*');
    }

    public function tryResolve(Request $request): ?Company
    {
        if ($this->needsCompanySelection($request)) {
            return null;
        }

        try {
            return $this->resolve($request);
        } catch (\Throwable) {
            return null;
        }
    }
}
