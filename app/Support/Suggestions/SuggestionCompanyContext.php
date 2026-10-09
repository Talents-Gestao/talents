<?php

declare(strict_types=1);

namespace App\Support\Suggestions;

use App\Enums\PermissionModule;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class SuggestionCompanyContext
{
    public const SESSION_KEY = 'suggestions_company_id';

    public function needsCompanySelection(Request $request): bool
    {
        $user = $request->user();

        if (! $user?->isSuperAdmin() || ! $request->routeIs('admin.suggestions.*')) {
            return false;
        }

        if ($user->contextCompany()?->hasSuggestionsEnabled()) {
            return false;
        }

        $sessionCompanyId = $request->session()->get(self::SESSION_KEY);

        if (! $sessionCompanyId) {
            return true;
        }

        $company = Company::query()->find($sessionCompanyId);

        return ! $company || ! $company->hasSuggestionsEnabled();
    }

    public function resolve(Request $request): Company
    {
        $user = $request->user();
        abort_unless($user, 403);

        $workspaceCompany = $user->contextCompany();

        if ($workspaceCompany && $workspaceCompany->hasModuleEnabled(PermissionModule::Sugestoes)) {
            return $workspaceCompany;
        }

        if ($user->isSuperAdmin() && $request->routeIs('admin.suggestions.*')) {
            $sessionCompanyId = $request->session()->get(self::SESSION_KEY);
            abort_unless($sessionCompanyId, 403, 'Selecione uma empresa para continuar.');

            $company = Company::query()->findOrFail($sessionCompanyId);
            abort_unless($company->hasSuggestionsEnabled(), 403);

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
            ->get(['id', 'name'])
            ->filter(fn (Company $company) => $company->hasSuggestionsEnabled())
            ->map(fn (Company $company) => $company->only(['id', 'name']))
            ->values();
    }

    public function isAdminContext(Request $request): bool
    {
        return $request->routeIs('admin.suggestions.*');
    }
}
