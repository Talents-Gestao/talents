<?php

namespace App\Policies;

use App\Models\TaskComment;
use App\Models\User;

class TaskCommentPolicy
{
    public function delete(User $user, TaskComment $comment): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return (int) $comment->user_id === (int) $user->id;
    }
}
