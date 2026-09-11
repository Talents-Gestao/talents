<?php

namespace App\Support;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

class AdminHomeResolver
{
    public function routeNameFor(User $user): ?string
    {
        if (! $user->isSuperAdmin()) {
            return null;
        }

        if (! $user->isActive()) {
            return null;
        }

        // Home admin exige view do módulo Painel (sempre concedida a super_admin ativo).
        if (! $user->canAccessAdmin(AdminPermissionModule::Dashboard, PermissionAction::View)) {
            return null;
        }

        return 'admin.dashboard';
    }

    public function urlFor(User $user): string
    {
        $routeName = $this->routeNameFor($user);

        if ($routeName === null) {
            abort(Response::HTTP_FORBIDDEN, 'Sem permissão para aceder ao painel administrativo.');
        }

        return route($routeName, absolute: false);
    }
}
