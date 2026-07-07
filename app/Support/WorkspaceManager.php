<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Enums\WorkspaceType;
use App\Models\User;
use App\Models\UserWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class WorkspaceManager
{
    public const SESSION_KEY = 'active_workspace_id';

    private const REQUEST_WORKSPACE_ATTRIBUTE = 'talents.active_workspace';

    /**
     * @return Collection<int, UserWorkspace>
     */
    public function activeWorkspacesFor(User $user): Collection
    {
        return $user->workspaces()
            ->where('is_active', true)
            ->with('company:id,name')
            ->orderBy('workspace_type')
            ->orderBy('id')
            ->get();
    }

    public function activeWorkspaceFor(User $user, ?Request $request = null): ?UserWorkspace
    {
        $request ??= request();

        if (! $request || ! $request->hasSession()) {
            return null;
        }

        $workspaceId = $request->session()->get(self::SESSION_KEY);

        if (! $workspaceId) {
            return null;
        }

        return $user->workspaces()
            ->where('id', $workspaceId)
            ->where('is_active', true)
            ->with(['company', 'permissions', 'adminPermissions'])
            ->first();
    }

    public function selectWorkspace(User $user, UserWorkspace $workspace, Request $request): void
    {
        abort_unless($workspace->user_id === $user->id && $workspace->is_active, 403);

        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, $workspace->id);
        }

        $user->setActiveWorkspace($workspace);
    }

    public function clearSelection(Request $request): void
    {
        if ($request->hasSession()) {
            $request->session()->forget(self::SESSION_KEY);
        }
    }

    public function redirectAfterLogin(User $user, Request $request): RedirectResponse
    {
        $workspaces = $this->activeWorkspacesFor($user);

        if ($workspaces->isEmpty()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Esta conta não possui nenhum ambiente de acesso ativo.',
            ]);
        }

        if ($workspaces->count() === 1) {
            $this->selectWorkspace($user, $workspaces->first(), $request);

            return $this->redirectForWorkspace($user, $workspaces->first());
        }

        $this->clearSelection($request);

        return redirect()->route('workspaces.select');
    }

    public function redirectForWorkspace(User $user, UserWorkspace $workspace): RedirectResponse
    {
        if ($workspace->isTalents()) {
            return redirect()->intended(app(AdminHomeResolver::class)->urlFor($user));
        }

        return redirect()->intended(route('client.dashboard', absolute: false));
    }

    /**
     * Resolve workspace ativo uma vez por request HTTP (evita queries duplicadas).
     */
    public function resolveActiveWorkspace(User $user, Request $request): ?UserWorkspace
    {
        if ($request->attributes->has(self::REQUEST_WORKSPACE_ATTRIBUTE)) {
            $workspace = $request->attributes->get(self::REQUEST_WORKSPACE_ATTRIBUTE);

            if ($workspace instanceof UserWorkspace) {
                $user->setActiveWorkspace($workspace);
            }

            return $workspace instanceof UserWorkspace ? $workspace : null;
        }

        $workspace = $this->ensureActiveWorkspace($user, $request);

        if ($workspace instanceof UserWorkspace) {
            $request->attributes->set(self::REQUEST_WORKSPACE_ATTRIBUTE, $workspace);
        }

        return $workspace;
    }

    /**
     * Garante workspace ativo na sessão; redireciona para seleção se necessário.
     */
    public function ensureActiveWorkspace(User $user, Request $request): ?UserWorkspace
    {
        if ($request->hasSession()) {
            $workspace = $this->activeWorkspaceFor($user, $request);

            if ($workspace) {
                $user->setActiveWorkspace($workspace);

                return $workspace;
            }
        }

        $workspaces = $this->activeWorkspacesFor($user);

        if ($workspaces->isEmpty()) {
            return null;
        }

        if ($workspaces->count() === 1) {
            $only = $workspaces->first();
            $this->selectWorkspace($user, $only, $request);

            return $only;
        }

        return null;
    }

    public function talentsWorkspaceFor(User $user): ?UserWorkspace
    {
        return $user->workspaces()
            ->where('workspace_type', WorkspaceType::Talents)
            ->first();
    }

    public function companyWorkspaceFor(User $user, int $companyId): ?UserWorkspace
    {
        return $user->workspaces()
            ->where('workspace_type', WorkspaceType::Company)
            ->where('company_id', $companyId)
            ->first();
    }

    public function createTalentsWorkspace(User $user, bool $isOwner = false, bool $isActive = true): UserWorkspace
    {
        return UserWorkspace::create([
            'user_id' => $user->id,
            'workspace_type' => WorkspaceType::Talents,
            'company_id' => null,
            'role' => UserRole::SuperAdmin,
            'is_owner' => $isOwner,
            'is_active' => $isActive,
        ]);
    }

    public function createCompanyWorkspace(
        User $user,
        int $companyId,
        UserRole $role,
        bool $isActive = true,
    ): UserWorkspace {
        return UserWorkspace::create([
            'user_id' => $user->id,
            'workspace_type' => WorkspaceType::Company,
            'company_id' => $companyId,
            'role' => $role,
            'is_owner' => false,
            'is_active' => $isActive,
        ]);
    }

    public function syncLegacyUserColumns(User $user, ?UserWorkspace $primary = null): void
    {
        $primary ??= $user->workspaces()
            ->orderByRaw("CASE workspace_type WHEN '".WorkspaceType::Company->value."' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();

        if (! $primary) {
            return;
        }

        $user->forceFill([
            'role' => $primary->role,
            'company_id' => $primary->isCompany() ? $primary->company_id : null,
            'is_owner' => $primary->isTalents() && $primary->isOwner(),
        ])->saveQuietly();
    }
}
