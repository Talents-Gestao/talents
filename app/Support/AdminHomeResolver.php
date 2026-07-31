<?php

namespace App\Support;

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

        // Admin Talents master: home única para todos.
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
