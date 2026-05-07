<?php

namespace App\Policies;

use App\Models\TaskBoard;
use App\Models\TaskCard;
use App\Models\User;
use App\Support\Tasks\TaskCardVisibility;

class TaskCardPolicy
{
    public function view(User $user, TaskCard $card): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $card->loadMissing('list.board');
        $board = $card->list?->board;
        if (! $board instanceof TaskBoard) {
            return false;
        }

        if (! $user->belongsToCompany() || ! $user->company?->hasTasksEnabled()) {
            return false;
        }

        if ((int) $board->company_id !== (int) $user->company_id) {
            return false;
        }

        return TaskCardVisibility::isVisibleToCompany($card);
    }

    public function update(User $user, TaskCard $card): bool
    {
        return $this->view($user, $card);
    }

    public function move(User $user, TaskCard $card): bool
    {
        return $this->view($user, $card);
    }

    public function comment(User $user, TaskCard $card): bool
    {
        return $this->view($user, $card);
    }

    public function attach(User $user, TaskCard $card): bool
    {
        return $this->view($user, $card);
    }
}
