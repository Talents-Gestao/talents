<?php

namespace App\Policies;

use App\Models\TaskBoard;
use App\Models\User;

class TaskBoardPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->belongsToCompany()
            && $user->company
            && $user->company->hasTasksEnabled();
    }

    public function view(User $user, TaskBoard $board): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $user->belongsToCompany() || ! $user->company?->hasTasksEnabled()) {
            return false;
        }

        return (int) $board->company_id === (int) $user->company_id;
    }

    /**
     * Quadros internos Talents (sem empresa): só super admin.
     */
    public function manageAsAdmin(User $user, TaskBoard $board): bool
    {
        return $user->isSuperAdmin();
    }
}
