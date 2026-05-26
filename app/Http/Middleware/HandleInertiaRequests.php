<?php

namespace App\Http\Middleware;

use App\Support\AdminHomeResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $shareStartedAt = microtime(true);

        $user = $request->user();

        $companyPayload = null;
        $permissions = [];

        if ($user) {
            if ($user->isCompanyUser()) {
                $user->loadMissing(['company', 'permissions']);
            } elseif ($user->isSuperAdmin()) {
                $user->loadMissing(['company', 'adminPermissions']);
            } else {
                $user->loadMissing('company');
            }

            $permissions = $user->permissionMatrixForFrontend();

            if ($user->company_id) {
                $company = $user->relationLoaded('company')
                    ? $user->company
                    : $user->company()->first();

                if ($company) {
                    $companyPayload = array_merge($company->toArray(), [
                        'has_methodology' => $company->hasMethodologyEnabled(),
                        'has_strategic_calendar' => $company->hasStrategicCalendarEnabled(),
                        'has_tasks' => $company->hasTasksEnabled(),
                        'active_permission_modules' => $company->activePermissionModuleValues(),
                    ]);
                }
            }
        }

        $shared = [
            ...parent::share($request),
            'auth' => [
                'user' => $user
                    ? [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role->value,
                        'company_id' => $user->company_id,
                        'company' => $companyPayload,
                        'permissions' => $permissions,
                        'admin_permissions' => $user->isSuperAdmin()
                            ? $user->adminPermissionMatrixForFrontend()
                            : null,
                        'is_owner' => $user->isSuperAdmin() && $user->isOwner(),
                        'admin_home_url' => $user->isSuperAdmin()
                            ? $this->adminHomeUrlFor($user)
                            : null,
                    ]
                    : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
                'contract_id' => fn () => $request->session()->get('contract_id'),
                'zapsign_sign_url' => fn () => $request->session()->get('zapsign_sign_url'),
            ],
        ];

        if (config('app.debug') && $request->headers->has('X-Inertia')) {
            Log::debug('[Inertia] HandleInertiaRequests::share()', [
                'path' => $request->path(),
                'share_ms' => round((microtime(true) - $shareStartedAt) * 1000, 2),
            ]);
        }

        return $shared;
    }

    private function adminHomeUrlFor(\App\Models\User $user): ?string
    {
        $routeName = app(AdminHomeResolver::class)->routeNameFor($user);

        return $routeName !== null ? route($routeName, absolute: false) : null;
    }
}
