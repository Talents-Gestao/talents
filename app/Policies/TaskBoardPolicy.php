<?php

namespace App\Policies;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Models\TaskBoard;
use App\Models\User;
use App\Support\Tasks\PortalTaskAccess;

class TaskBoardPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->belongsToCompany()
            && $user->contextCompany()
            && $user->contextCompany()->hasTasksEnabled();
    }

    public function view(User $user, TaskBoard $board): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Portal: quadro da empresa, convidado do quadro ou com algum cartão visível (PortalTaskAccess).
        return ($user->isCompanyAdmin() || $user->isCompanyUser())
            && PortalTaskAccess::canSeeBoard($user, $board);
    }

    /**
     * Quadros internos Talents (sem empresa): só super admin.
     */
    public function manageAsAdmin(User $user, TaskBoard $board): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Reordenar a listagem Admin (internos ou de empresas).
     * Utilizadores de empresa não passam: canAccessAdmin exige super_admin.
     */
    public function reorder(User $user): bool
    {
        return $user->canAccessAdmin(AdminPermissionModule::Tarefas, PermissionAction::Edit);
    }
}
