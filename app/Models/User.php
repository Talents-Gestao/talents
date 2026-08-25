<?php

namespace App\Models;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Enums\UserRole;
use App\Enums\WorkspaceType;
use App\Support\WorkspaceManager;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
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
        'commission_percent',
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
            'commission_percent' => 'float',
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

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeWithActiveTalentsWorkspace(Builder $query): Builder
    {
        return $query->whereHas(
            'workspaces',
            fn (Builder $q) => $q
                ->where('workspace_type', WorkspaceType::Talents)
                ->where('is_active', true),
        );
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

    public function talentsWorkspace(): ?UserWorkspace
    {
        $active = $this->activeWorkspace();
        if ($active?->isTalents()) {
            return $active;
        }

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

        // Feedbacks internos: RH e líder (company_user) acedem quando o módulo está ativo.
        if ($module === PermissionModule::Feedbacks) {
            return match ($action) {
                PermissionAction::Delete => $this->permissions()
                    ->where('module', $module->value)
                    ->where('action', $action->value)
                    ->exists(),
                default => true,
            };
        }

        // Acompanhamento: company_user vê, comenta e gerencia o funil; só não cria processos.
        if ($module === PermissionModule::Acompanhamento) {
            return match ($action) {
                PermissionAction::View, PermissionAction::Edit, PermissionAction::Delete => true,
                default => false,
            };
        }

        // Férias: apenas administradores da empresa (company_admin já retornou true acima).
        if ($module === PermissionModule::Ferias) {
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

        if (! $this->isActive()) {
            return false;
        }

        $talentsWorkspace = $this->talentsWorkspace();
        if ($talentsWorkspace === null) {
            return false;
        }

        // Proprietário Talents: acesso master (não depende da matriz).
        if ($talentsWorkspace->isOwner() || $this->isOwner()) {
            return true;
        }

        $permissions = $talentsWorkspace->relationLoaded('adminPermissions')
            ? $talentsWorkspace->adminPermissions
            : $talentsWorkspace->adminPermissions()->get();

        $hasGrant = static function (string $moduleValue) use ($permissions, $action): bool {
            foreach ($permissions as $p) {
                $mod = $p->module instanceof AdminPermissionModule
                    ? $p->module->value
                    : (string) $p->module;
                $act = $p->action instanceof PermissionAction
                    ? $p->action->value
                    : (string) $p->action;
                if ($mod === $moduleValue && $act === $action->value) {
                    return true;
                }
            }

            return false;
        };

        if ($hasGrant($module->value)) {
            return true;
        }

        // Grants legados (ex.: financeiro) ainda cobrem os submódulos até o backfill.
        foreach ($module->legacyParents() as $parent) {
            if ($hasGrant($parent->value)) {
                return true;
            }
        }

        return false;
    }

    public function hasAllAdminPermissions(): bool
    {
        if (! $this->isSuperAdmin() || ! $this->isActive()) {
            return false;
        }

        $talentsWorkspace = $this->talentsWorkspace();
        if ($talentsWorkspace === null) {
            return false;
        }

        if ($talentsWorkspace->isOwner() || $this->isOwner()) {
            return true;
        }

        $needed = count(AdminPermissionModule::all()) * count(PermissionAction::all());
        $have = $talentsWorkspace->adminPermissions()->count();

        return $have >= $needed;
    }

    /**
     * @return array<string, mixed>
     */
    public function adminPermissionMatrixForFrontend(): array
    {
        if (! $this->isSuperAdmin() || ! $this->isActive()) {
            return [];
        }

        $talentsWorkspace = $this->talentsWorkspace();
        if ($talentsWorkspace === null) {
            return [];
        }

        if ($talentsWorkspace->isOwner() || $this->isOwner()) {
            return ['*' => true];
        }

        $matrix = [];
        $rows = $talentsWorkspace->relationLoaded('adminPermissions')
            ? $talentsWorkspace->adminPermissions
            : $talentsWorkspace->adminPermissions()->get();

        foreach ($rows as $p) {
            $mod = $p->module instanceof AdminPermissionModule
                ? $p->module->value
                : (string) $p->module;
            $act = $p->action instanceof PermissionAction
                ? $p->action->value
                : (string) $p->action;
            $matrix[$mod] ??= [];
            $matrix[$mod][] = $act;
        }

        foreach ($matrix as $key => $actions) {
            $matrix[$key] = array_values(array_unique($actions));
        }

        // Expõe filhos quando ainda existem grants legados (pré-migration / backfill).
        foreach (AdminPermissionModule::legacyExpansionMap() as $parentValue => $children) {
            if (! isset($matrix[$parentValue])) {
                continue;
            }

            foreach ($children as $child) {
                $matrix[$child->value] = array_values(array_unique(array_merge(
                    $matrix[$child->value] ?? [],
                    $matrix[$parentValue],
                )));
            }
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

        // Alinhado a canAccess(): company_user gerencia o funil; Create só company_admin / Talents.
        if (in_array(PermissionModule::Acompanhamento->value, $active, true)) {
            $matrix[PermissionModule::Acompanhamento->value] = array_values(array_unique(array_merge(
                $matrix[PermissionModule::Acompanhamento->value] ?? [],
                [
                    PermissionAction::View->value,
                    PermissionAction::Edit->value,
                    PermissionAction::Delete->value,
                ],
            )));
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
