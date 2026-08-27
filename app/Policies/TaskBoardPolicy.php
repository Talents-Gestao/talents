<?php

namespace App\Policies;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Models\TaskBoard;
use App\Models\TaskCard;
use App\Models\User;

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

        if (! $user->belongsToCompany() || ! $user->contextCompany()?->hasTasksEnabled()) {
            return false;
        }

        $companyId = (int) $user->contextCompanyId();

        if ($user->isCompanyAdmin()) {
            if ($board->company_id === null) {
                return true;
            }

            return (int) $board->company_id === $companyId;
        }

        if ($user->isCompanyUser()) {
            if ($board->company_id !== null && (int) $board->company_id !== $companyId) {
                return false;
            }

            if ($board->hasMember($user->id)) {
                return true;
            }

            return TaskCard::query()
                ->where('is_archived', false)
                ->visibleToCompany($companyId)
                ->whereHas('members', fn ($q) => $q->where('users.id', $user->id))
                ->whereHas('list', fn ($q) => $q->where('board_id', $board->id)->where('is_archived', false))
                ->exists();
        }

        return false;
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
