<?php

namespace App\Models;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Enums\UserRole;
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    public function adminPermissions(): HasMany
    {
        return $this->hasMany(AdminUserPermission::class);
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function hasCompletedRegistration(): bool
    {
        return $this->password_set_at !== null;
    }

    public function isCompanyUser(): bool
    {
        return $this->role === UserRole::CompanyUser;
    }

    public function canAccess(PermissionModule $module, PermissionAction $action): bool
    {
        if ($this->isOwner()) {
            return true;
        }

        if (! $this->isActive()) {
            return false;
        }

        $company = $this->relationLoaded('company') ? $this->company : $this->company()->first();

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

    /**
     * Verdadeiro quando o super admin tem todas as combinações módulo × ação (ou é proprietário).
     */
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
     * Matriz para o sidebar admin: módulo => lista de ações (values).
     * Owner: ['*' => true].
     *
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
     * Matriz para o frontend: módulo => lista de ações (values).
     * Owner (super admin): ['*' => true].
     *
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

        $company = $this->relationLoaded('company') ? $this->company : $this->company()->first();

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
        return $this->role === UserRole::SuperAdmin;
    }

    public function isOwner(): bool
    {
        return (bool) $this->is_owner;
    }

    public function isCompanyAdmin(): bool
    {
        return $this->role === UserRole::CompanyAdmin;
    }

    public function belongsToCompany(): bool
    {
        return in_array($this->role, [UserRole::CompanyAdmin, UserRole::CompanyUser], true);
    }
}
