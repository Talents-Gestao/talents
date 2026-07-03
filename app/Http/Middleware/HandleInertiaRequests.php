<?php

namespace App\Http\Middleware;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Support\AdminHomeResolver;
use App\Support\Notices\UnreadNoticeCounter;
use App\Support\WorkspaceManager;
use Illuminate\Http\Request;
use Illuminate\Session\CacheBasedSessionHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
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

        $permissions = [];

        if ($user) {
            $workspace = $this->workspaceManager->ensureActiveWorkspace($user, $request);

            if ($workspace) {
                $user->setActiveWorkspace($workspace);

                if ($workspace->isCompany()) {
                    $workspace->loadMissing(['company', 'permissions']);
                } elseif ($workspace->isTalents()) {
                    $workspace->loadMissing('adminPermissions');
                }
            }

            $permissions = $user->permissionMatrixForFrontend();
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
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
                'contract_id' => fn () => $request->session()->get('contract_id'),
                'zapsign_sign_url' => fn () => $request->session()->get('zapsign_sign_url'),
            ],
            'sessionExpiry' => Inertia::always(fn () => $this->sessionMetaForFrontend($request)),
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

    /**
     * @return array<string, int>|null
     */
    private function sessionMetaForFrontend(Request $request): ?array
    {
        if ($request->user() === null) {
            return null;
        }

        $lifetimeMinutes = (int) config('session.lifetime');
        $warningMinutes = (int) config('session.warning_minutes', 5);
        $lifetimeSeconds = $lifetimeMinutes * 60;

        return [
            'expires_at' => $this->sessionExpiresAtMs($request, $lifetimeSeconds),
            'lifetime_minutes' => $lifetimeMinutes,
            'warning_minutes' => $warningMinutes,
        ];
    }

    private function sessionExpiresAtMs(Request $request, int $lifetimeSeconds): int
    {
        $driver = config('session.driver');

        if ($driver === 'database') {
            $lastActivity = DB::table(config('session.table', 'sessions'))
                ->where('id', $request->session()->getId())
                ->value('last_activity');

            if ($lastActivity !== null) {
                return ((int) $lastActivity + $lifetimeSeconds) * 1000;
            }
        }

        $handler = $request->session()->getHandler();

        if ($handler instanceof CacheBasedSessionHandler) {
            $store = $handler->getCache()->getStore();

            if (method_exists($store, 'connection')) {
                $connection = $store->connection();
                $key = (method_exists($store, 'getPrefix') ? $store->getPrefix() : '').$request->session()->getId();
                $ttl = $connection->ttl($key);

                if (is_int($ttl) && $ttl > 0) {
                    return (now()->getTimestamp() + $ttl) * 1000;
                }
            }
        }

        return now()->addSeconds($lifetimeSeconds)->getTimestamp() * 1000;
    }
}
