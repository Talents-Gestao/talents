<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\WorkspaceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserWorkspace extends Model
{
    protected $fillable = [
        'user_id',
        'workspace_type',
        'company_id',
        'role',
        'is_owner',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'workspace_type' => WorkspaceType::class,
            'role' => UserRole::class,
            'is_owner' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function isTalents(): bool
    {
        return $this->workspace_type === WorkspaceType::Talents;
    }

    public function isCompany(): bool
    {
        return $this->workspace_type === WorkspaceType::Company;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isCompanyAdmin(): bool
    {
        return $this->role === UserRole::CompanyAdmin;
    }

    public function isCompanyUser(): bool
    {
        return $this->role === UserRole::CompanyUser;
    }

    public function isOwner(): bool
    {
        return $this->isTalents() && (bool) $this->is_owner;
    }

    /**
     * @return array<string, mixed>
     */
    public function toFrontendArray(): array
    {
        $companyName = null;
        if ($this->isCompany() && $this->relationLoaded('company')) {
            $companyName = $this->company?->name;
        } elseif ($this->isCompany() && $this->company_id) {
            $companyName = $this->company()->value('name');
        }

        return [
            'id' => $this->id,
            'workspace_type' => $this->workspace_type->value,
            'workspace_label' => $this->isTalents()
                ? WorkspaceType::Talents->label()
                : ($companyName ?? WorkspaceType::Company->label()),
            'company_id' => $this->company_id,
            'company_name' => $companyName,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'is_owner' => $this->isOwner(),
        ];
    }
}
