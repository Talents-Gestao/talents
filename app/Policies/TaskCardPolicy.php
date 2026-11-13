<?php

namespace App\Policies;

use App\Models\TaskCard;
use App\Models\User;
use App\Support\Tasks\PortalTaskAccess;

/**
 * Admin (super admin) faz tudo. Portal: só cartões visíveis ao usuário e só o que a atribuição ao cartão, o papel no
 * quadro e a permissão do módulo deixam (PortalTaskAccess).
 */
class TaskCardPolicy
{
    public function view(User $user, TaskCard $card): bool
    {
        return $user->isSuperAdmin() || PortalTaskAccess::canSeeCard($user, $card);
    }

    public function update(User $user, TaskCard $card): bool
    {
        return $user->isSuperAdmin() || PortalTaskAccess::allows($user, $card, 'edit');
    }

    public function addChecklistItem(User $user, TaskCard $card): bool
    {
        return $user->isSuperAdmin() || PortalTaskAccess::allows($user, $card, 'checklist');
    }

    public function move(User $user, TaskCard $card): bool
    {
        return $user->isSuperAdmin() || PortalTaskAccess::allows($user, $card, 'move');
    }

    public function comment(User $user, TaskCard $card): bool
    {
        return $user->isSuperAdmin() || PortalTaskAccess::allows($user, $card, 'comment');
    }

    public function attach(User $user, TaskCard $card): bool
    {
        return $user->isSuperAdmin() || PortalTaskAccess::allows($user, $card, 'attach');
    }

    public function removeAttachment(User $user, TaskCard $card): bool
    {
        return $user->isSuperAdmin() || PortalTaskAccess::allows($user, $card, 'remove_attachment');
    }
}
