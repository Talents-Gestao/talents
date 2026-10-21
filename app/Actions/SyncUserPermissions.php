<?php

namespace App\Actions;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\Company;
use App\Models\UserWorkspace;
use Illuminate\Validation\ValidationException;

class SyncUserPermissions
{
    /**
     * @param  array<int, array{module: string, action: string}>  $permissions
     *
     * @throws ValidationException quando uma ação restrita vem num módulo que não a aceita
     */
    public function execute(UserWorkspace $workspace, Company $company, array $permissions): void
    {
        $allowed = $company->activePermissionModuleValues();
        $rows = [];

        foreach ($permissions as $row) {
            if (! isset($row['module'], $row['action'])) {
                continue;
            }

            $mod = PermissionModule::from($row['module']);
            $act = PermissionAction::from($row['action']);

            if (! $act->allowedFor($mod)) {
                throw ValidationException::withMessages([
                    'permissions' => "A ação «{$act->label()}» não existe no módulo {$mod->label()}.",
                ]);
            }

            if (! in_array($mod->value, $allowed, true)) {
                continue;
            }

            $rows[] = ['module' => $mod->value, 'action' => $act->value];
        }

        $workspace->permissions()->delete();

        foreach ($rows as $row) {
            $workspace->permissions()->create($row);
        }
    }
}
