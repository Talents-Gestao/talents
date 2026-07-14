<?php

namespace App\Actions;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\Company;
use App\Models\UserWorkspace;

class SyncUserPermissions
{
    /**
     * @param  array<int, array{module: string, action: string}>  $permissions
     */
    public function execute(UserWorkspace $workspace, Company $company, array $permissions): void
    {
        $allowed = $company->activePermissionModuleValues();

        $workspace->permissions()->delete();

        foreach ($permissions as $row) {
            if (! isset($row['module'], $row['action'])) {
                continue;
            }

            $mod = PermissionModule::from($row['module']);
            $act = PermissionAction::from($row['action']);

            if (! in_array($mod->value, $allowed, true)) {
                continue;
            }

            // Empresa só consulta desligamento; criar/editar/excluir ficam no admin Talents.
            if ($mod === PermissionModule::Desligamento && $act !== PermissionAction::View) {
                continue;
            }

            $workspace->permissions()->create([
                'module' => $mod->value,
                'action' => $act->value,
            ]);
        }
    }
}
