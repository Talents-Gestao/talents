<?php

namespace App\Actions;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\Company;
use App\Models\User;

class SyncUserPermissions
{
    /**
     * @param  array<int, array{module: string, action: string}>  $permissions
     */
    public function execute(User $user, Company $company, array $permissions): void
    {
        $allowed = $company->activePermissionModuleValues();

        $user->permissions()->delete();

        foreach ($permissions as $row) {
            if (! isset($row['module'], $row['action'])) {
                continue;
            }

            $mod = PermissionModule::from($row['module']);
            $act = PermissionAction::from($row['action']);

            if (! in_array($mod->value, $allowed, true)) {
                continue;
            }

            $user->permissions()->create([
                'module' => $mod->value,
                'action' => $act->value,
            ]);
        }
    }
}
