<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;

class CompanyPortal
{
    public const SESSION_KEY = 'company_portal_slug';

    public static function findEnabled(string $slug): ?Company
    {
        $slug = strtolower(trim($slug));

        if ($slug === '') {
            return null;
        }

        return Company::query()
            ->where('portal_slug', $slug)
            ->where('portal_enabled', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Marca do workspace ativo e URL de retorno quando a entrada foi pelo portal.
     *
     * @return array{slug: string, name: string|null, logo_url: string|null, primary_color: string|null, login_url: string|null}|null
     */
    public static function shareForRequest(Request $request, ?User $user): ?array
    {
        $sessionSlug = null;

        if ($request->hasSession()) {
            $stored = $request->session()->get(self::SESSION_KEY);
            if (is_string($stored) && $stored !== '') {
                $sessionSlug = $stored;
            }
        }

        $company = $user?->contextCompany();
        $showBrand = $company instanceof Company && $company->hasActivePortal();

        if (! $showBrand && $sessionSlug === null) {
            return null;
        }

        return [
            'slug' => $showBrand ? (string) $company->portal_slug : (string) $sessionSlug,
            'name' => $showBrand ? $company->portalBrandName() : null,
            'logo_url' => $showBrand ? $company->logo_url : null,
            'primary_color' => $showBrand ? $company->brand_primary_color : null,
            'login_url' => $sessionSlug !== null
                ? route('portal.login', ['slug' => $sessionSlug], absolute: false)
                : null,
        ];
    }
}
