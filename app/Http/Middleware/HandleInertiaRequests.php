<?php

namespace App\Http\Middleware;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Support\AdminHomeResolver;
use App\Support\Notices\UnreadNoticeCounter;
use App\Support\WorkspaceManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(
        private WorkspaceManager $workspaceManager,
    ) {}

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
        $workspacePayload = null;
        $availableWorkspaces = [];

        if ($user) {
            $workspace = $this->workspaceManager->ensureActiveWorkspace($user, $request);

            if ($workspace) {
                $user->setActiveWorkspace($workspace);
                $workspacePayload = $workspace->toFrontendArray();

                if ($workspace->isCompany()) {
                    $workspace->loadMissing(['company', 'permissions']);
                } elseif ($workspace->isTalents()) {
                    $workspace->loadMissing('adminPermissions');
                }
            }

            $availableWorkspaces = $this->workspaceManager
                ->activeWorkspacesFor($user)
                ->map(fn ($w) => $w->toFrontendArray())
                ->values()
                ->all();

            $permissions = $user->permissionMatrixForFrontend();

            $company = $user->contextCompany();

            if ($company) {
                $companyPayload = array_merge($company->toArray(), [
                    'has_methodology' => $company->hasMethodologyEnabled(),
                    'has_strategic_calendar' => $company->hasStrategicCalendarEnabled(),
                    'has_tasks' => $company->hasTasksEnabled(),
                    'has_complaints' => $company->hasComplaintsEnabled(),
                    'active_permission_modules' => $company->activePermissionModuleValues(),
                ]);
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
                        'role' => $user->contextRole()->value,
                        'company_id' => $user->contextCompanyId(),
                        'company' => $companyPayload,
                        'permissions' => $permissions,
                        'admin_permissions' => $user->isSuperAdmin()
                            ? $user->adminPermissionMatrixForFrontend()
                            : null,
                        'is_owner' => $user->isSuperAdmin() && $user->isOwner(),
                        'admin_home_url' => $user->isSuperAdmin()
                            ? $this->adminHomeUrlFor($user)
                            : null,
                        'can_commercial_settings' => $user->isSuperAdmin()
                            && $user->canAccessAdmin(AdminPermissionModule::Comercial, PermissionAction::View),
                    ]
                    : null,
                'workspace' => $workspacePayload,
                'available_workspaces' => $availableWorkspaces,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
                'contract_id' => fn () => $request->session()->get('contract_id'),
                'zapsign_sign_url' => fn () => $request->session()->get('zapsign_sign_url'),
            ],
            'nav' => [
                'unread_notices_count' => fn () => $user && $user->contextCompanyId()
                    ? app(UnreadNoticeCounter::class)->forUser($user)
                    : 0,
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
