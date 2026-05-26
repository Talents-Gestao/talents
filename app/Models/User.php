<?php

namespace App\Models;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Enums\UserRole;
use App\Enums\WorkspaceType;
use App\Support\WorkspaceManager;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected ?UserWorkspace $activeWorkspaceCache = null;

    protected $fillable = [
        'name',
        'email',
        'password',
        'password_set_at',
        'role',
        'company_id',
        'is_active',
        'is_commercial',
        'is_owner',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password_set_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'is_commercial' => 'boolean',
            'is_owner' => 'boolean',
        ];
    }

    public function setActiveWorkspace(?UserWorkspace $workspace): void
    {
        $this->activeWorkspaceCache = $workspace;
    }

    public function activeWorkspace(): ?UserWorkspace
    {
        if ($this->activeWorkspaceCache) {
            return $this->activeWorkspaceCache;
        }

        $manager = app(WorkspaceManager::class);

        return $this->activeWorkspaceCache = $manager->activeWorkspaceFor($this);
    }

    public function workspaces(): HasMany
    {
        return $this->hasMany(UserWorkspace::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Empresa do contexto ativo (workspace company) ou coluna legada.
     */
    public function contextCompany(): ?Company
    {
        $workspace = $this->activeWorkspace();

        if ($workspace?->isCompany()) {
            return $workspace->relationLoaded('company')
                ? $workspace->company
                : $workspace->company()->first();
        }

        if ($this->attributes['company_id'] ?? null) {
            return $this->relationLoaded('company')
                ? $this->getRelation('company')
                : $this->company()->first();
        }

        return null;
    }

    public function permissions(): HasMany
    {
        $workspace = $this->activeWorkspace();

        if ($workspace) {
            return $workspace->permissions();
        }

        return $this->hasMany(UserPermission::class, 'user_workspace_id')
            ->whereRaw('1 = 0');
    }

    public function adminPermissions(): HasMany
    {
        $workspace = $this->activeWorkspace();

        if ($workspace) {
            return $workspace->adminPermissions();
        }

        return $this->hasMany(AdminUserPermission::class, 'user_workspace_id')
            ->whereRaw('1 = 0');
    }

    public function talentsWorkspace(): ?UserWorkspace
    {
        return $this->workspaces()
            ->where('workspace_type', WorkspaceType::Talents)
            ->first();
    }

    public function companyWorkspace(?int $companyId = null): ?UserWorkspace
    {
        $query = $this->workspaces()->where('workspace_type', WorkspaceType::Company);

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        return $query->first();
    }

    public function isActive(): bool
    {
        if (! (bool) $this->is_active) {
            return false;
        }

        $workspace = $this->activeWorkspace();

        return $workspace ? (bool) $workspace->is_active : true;
    }

    public function hasCompletedRegistration(): bool
    {
        return $this->password_set_at !== null;
    }

    public function isCompanyUser(): bool
    {
        return $this->contextRole() === UserRole::CompanyUser;
    }

    public function canAccess(PermissionModule $module, PermissionAction $action): bool
    {
        if ($this->isOwner()) {
            return true;
        }

        if (! $this->isActive()) {
            return false;
        }

        $company = $this->contextCompany();

        if (! $company || ! $company->hasModuleEnabled($module)) {
            return false;
        }

        if ($this->isCompanyAdmin()) {
            return true;
        }

        if (! $this->isCompanyUser()) {
            return false;
        }

        return $this->permissions()
            ->where('module', $module->value)
            ->where('action', $action->value)
            ->exists();
    }

    public function canAccessAdmin(AdminPermissionModule $module, PermissionAction $action): bool
    {
        if (! $this->isSuperAdmin()) {
            return false;
        }

        if ($this->isOwner()) {
            return true;
        }

        if (! $this->isActive()) {
            return false;
        }

        if ($this->relationLoaded('adminPermissions')) {
            return $this->adminPermissions->contains(
                fn (AdminUserPermission $p) => $p->module === $module && $p->action === $action
            );
        }

        return $this->adminPermissions()
            ->where('module', $module->value)
            ->where('action', $action->value)
            ->exists();
    }

    public function hasAllAdminPermissions(): bool
    {
        if (! $this->isSuperAdmin()) {
            return false;
        }

        foreach (AdminPermissionModule::all() as $module) {
            foreach (PermissionAction::all() as $action) {
                if (! $this->canAccessAdmin($module, $action)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function adminPermissionMatrixForFrontend(): array
    {
        if (! $this->isSuperAdmin()) {
            return [];
        }

        if ($this->isOwner()) {
            return ['*' => true];
        }

        $matrix = [];
        $rows = $this->relationLoaded('adminPermissions')
            ? $this->adminPermissions
            : $this->adminPermissions()->get();

        foreach ($rows as $p) {
            $matrix[$p->module->value] ??= [];
            $matrix[$p->module->value][] = $p->action->value;
        }

        foreach ($matrix as $key => $actions) {
            $matrix[$key] = array_values(array_unique($actions));
        }

        return $matrix;
    }

    /**
     * @return array<string, mixed>
     */
    public function permissionMatrixForFrontend(): array
    {
        if ($this->isOwner()) {
            return ['*' => true];
        }

        if ($this->isSuperAdmin()) {
            return [];
        }

        $company = $this->contextCompany();

        if (! $company) {
            return [];
        }

        $active = $company->activePermissionModuleValues();

        if ($this->isCompanyAdmin()) {
            $matrix = [];
            $allActions = array_map(static fn (PermissionAction $a) => $a->value, PermissionAction::all());
            foreach ($active as $modVal) {
                $matrix[$modVal] = $allActions;
            }

            return $matrix;
        }

        if (! $this->isCompanyUser()) {
            return [];
        }

        $matrix = [];
        $rows = $this->relationLoaded('permissions')
            ? $this->permissions
            : $this->permissions()->get();

        foreach ($rows as $p) {
            if (! in_array($p->module->value, $active, true)) {
                continue;
            }
            $matrix[$p->module->value] ??= [];
            $matrix[$p->module->value][] = $p->action->value;
        }

        foreach ($matrix as $key => $actions) {
            $matrix[$key] = array_values(array_unique($actions));
        }

        return $matrix;
    }

    public function isSuperAdmin(): bool
    {
        return $this->contextRole() === UserRole::SuperAdmin;
    }

    public function isOwner(): bool
    {
        $workspace = $this->activeWorkspace();

        if ($workspace) {
            return $workspace->isOwner();
        }

        return (bool) $this->is_owner;
    }

    public function isCompanyAdmin(): bool
    {
        return $this->contextRole() === UserRole::CompanyAdmin;
    }

    public function belongsToCompany(): bool
    {
        return in_array($this->contextRole(), [UserRole::CompanyAdmin, UserRole::CompanyUser], true);
    }

    public function contextRole(): UserRole
    {
        $workspace = $this->activeWorkspace();

        if ($workspace) {
            return $workspace->role;
        }

        return $this->role;
    }

    public function contextCompanyId(): ?int
    {
        $workspace = $this->activeWorkspace();

        if ($workspace?->isCompany()) {
            return $workspace->company_id;
        }

        return $this->attributes['company_id'] ?? null;
    }

    public function getAttribute($key): mixed
    {
        if ($key === 'company_id') {
            $workspace = $this->activeWorkspace();

            if ($workspace?->isCompany()) {
                return $workspace->company_id;
            }
        }

        return parent::getAttribute($key);
    }
}
