<?php

declare(strict_types=1);

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
        if (! $workspace->isTalents()) {
            return;
        }

        // Proprietário: acesso total implícito — não grava linhas.
        if ($workspace->isOwner()) {
            $workspace->adminPermissions()->delete();

            return;
        }

        $allowedModules = array_map(
            static fn (AdminPermissionModule $m) => $m->value,
            AdminPermissionModule::all(),
        );
        $allowedActions = array_map(
            static fn (PermissionAction $a) => $a->value,
            PermissionAction::all(),
        );

        $workspace->adminPermissions()->delete();

        $seen = [];
        foreach ($permissions as $row) {
            if (! isset($row['module'], $row['action'])) {
                continue;
            }

            $module = (string) $row['module'];
            $action = (string) $row['action'];
            $key = $module.'|'.$action;

            if (isset($seen[$key])) {
                continue;
            }

            if (! in_array($module, $allowedModules, true) || ! in_array($action, $allowedActions, true)) {
                continue;
            }

            $seen[$key] = true;
            $workspace->adminPermissions()->create([
                'module' => $module,
                'action' => $action,
            ]);
        }
    }

    /**
     * Conjunto completo (todos os módulos × ações) — útil no backfill / defaults.
     *
     * @return list<array{module: string, action: string}>
     */
    public static function allGrants(): array
    {
        $rows = [];
        foreach (AdminPermissionModule::all() as $module) {
            foreach (PermissionAction::all() as $action) {
                $rows[] = [
                    'module' => $module->value,
                    'action' => $action->value,
                ];
            }
        }

        return $rows;
    }

    /**
     * Default para novo colaborador: só Painel (não Financeiro/Comercial até alguém marcar).
     *
     * @return list<array{module: string, action: string}>
     */
    public static function defaultNewCollaboratorGrants(): array
    {
        $rows = [];
        foreach (PermissionAction::all() as $action) {
            $rows[] = [
                'module' => AdminPermissionModule::Dashboard->value,
                'action' => $action->value,
            ];
        }

        return $rows;
    }
}
