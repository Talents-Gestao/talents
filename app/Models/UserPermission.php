<?php

namespace App\Models;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPermission extends Model
{
    protected $fillable = [
        'user_workspace_id',
        'module',
        'action',
    ];

    protected function casts(): array
    {
        return [
            'module' => PermissionModule::class,
            'action' => PermissionAction::class,
        ];
    }

    public function userWorkspace(): BelongsTo
    {
        return $this->belongsTo(UserWorkspace::class);
    }
}
