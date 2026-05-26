<?php

namespace App\Actions;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Models\UserWorkspace;

class SyncAdminUserPermissions
{
    /**
     * @param  array<int, array{module: string, action: string}>  $permissions
     */
    public function execute(UserWorkspace $workspace, array $permissions): void
    {
        $workspace->adminPermissions()->delete();

        foreach ($permissions as $row) {
            if (! isset($row['module'], $row['action'])) {
                continue;
            }

            $mod = AdminPermissionModule::from($row['module']);
            $act = PermissionAction::from($row['action']);

            $workspace->adminPermissions()->create([
                'module' => $mod->value,
                'action' => $act->value,
            ]);
        }
    }
}
