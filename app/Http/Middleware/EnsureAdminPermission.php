<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminPermission
{
    /**
     * @param  string  $module  Um módulo ou vários separados por vírgula (OR).
     * @param  string  $action  view|create|edit|delete ou "auto" (mapeia pelo método HTTP).
     */
    public function handle(Request $request, Closure $next, string $module, string $action = 'auto'): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isSuperAdmin()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        if ($action === 'auto') {
            $actionEnum = match ($request->method()) {
                'POST' => PermissionAction::Create,
                'PUT', 'PATCH' => PermissionAction::Edit,
                'DELETE' => PermissionAction::Delete,
                default => PermissionAction::View,
            };
        } else {
            $actionEnum = PermissionAction::from($action);
        }

        $modules = array_values(array_filter(array_map(
            static fn (string $m) => trim($m),
            explode(',', $module),
        )));

        foreach ($modules as $moduleValue) {
            $moduleEnum = AdminPermissionModule::from($moduleValue);
            if ($user->canAccessAdmin($moduleEnum, $actionEnum)) {
                return $next($request);
            }
        }

        abort(Response::HTTP_FORBIDDEN, 'Sem permissão para esta área.');
    }
}
