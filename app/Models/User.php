<?php

namespace App\Models;

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
        'role',
        'company_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
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

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function isCompanyUser(): bool
    {
        return $this->role === UserRole::CompanyUser;
    }

    public function canAccess(PermissionModule $module, PermissionAction $action): bool
    {
        if ($this->isSuperAdmin()) {
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

    /**
     * Matriz para o frontend: módulo => lista de ações (values).
     * SuperAdmin: ['*' => true].
     *
     * @return array<string, mixed>
     */
    public function permissionMatrixForFrontend(): array
    {
        if ($this->isSuperAdmin()) {
            return ['*' => true];
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

    public function isCompanyAdmin(): bool
    {
        return $this->role === UserRole::CompanyAdmin;
    }

    public function belongsToCompany(): bool
    {
        return in_array($this->role, [UserRole::CompanyAdmin, UserRole::CompanyUser], true);
    }
}
