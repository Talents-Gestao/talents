<?php

namespace App\Models;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminUserPermission extends Model
{
    protected $fillable = [
        'user_workspace_id',
        'module',
        'action',
    ];

    protected function casts(): array
    {
        return [
            'module' => AdminPermissionModule::class,
            'action' => PermissionAction::class,
        ];
    }

    public function userWorkspace(): BelongsTo
    {
        return $this->belongsTo(UserWorkspace::class);
    }
}
