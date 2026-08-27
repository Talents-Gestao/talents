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
     * @param  string  $module  Um módulo ou vários separados por "|" (OR).
     * @param  string  $action  view|create|edit|delete|"auto" (HTTP), ou outro módulo
     *                          legado separado por vírgula (também OR + action=auto).
     *
     * Exemplos:
     * - admin.can:tarefas
     * - admin.can:tarefas,edit
     * - admin.can:financeiro_vendas|financeiro_contas_a_receber
     */
    public function handle(Request $request, Closure $next, string $module, string $action = 'auto'): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isSuperAdmin()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        [$modules, $actionEnum] = $this->resolveModulesAndAction($request, $module, $action);

        foreach ($modules as $moduleValue) {
            $moduleEnum = AdminPermissionModule::tryFrom($moduleValue);
            if ($moduleEnum === null) {
                continue;
            }
            if ($user->canAccessAdmin($moduleEnum, $actionEnum)) {
                return $next($request);
            }
        }

        abort(Response::HTTP_FORBIDDEN, 'Sem permissão para esta área.');
    }

    /**
     * Laravel separa parâmetros de middleware por vírgula, por isso vários módulos
     * devem usar "|" (ex.: a|b). Aceita também o formato legado "a,b" quando o
     * segundo segmento não é uma PermissionAction válida.
     *
     * @return array{0: list<string>, 1: PermissionAction}
     */
    private function resolveModulesAndAction(Request $request, string $module, string $action): array
    {
        $modules = array_values(array_filter(array_map(
            static fn (string $m) => trim($m),
            preg_split('/[|,]/', $module) ?: [],
        )));

        if ($action === 'auto') {
            return [$modules, $this->actionFromHttpMethod($request)];
        }

        $actionEnum = PermissionAction::tryFrom($action);
        if ($actionEnum !== null) {
            return [$modules, $actionEnum];
        }

        // Legado: admin.can:mod_a,mod_b — Laravel passa mod_b como $action.
        $extra = trim($action);
        if ($extra !== '') {
            $modules[] = $extra;
        }

        return [$modules, $this->actionFromHttpMethod($request)];
    }

    private function actionFromHttpMethod(Request $request): PermissionAction
    {
        return match ($request->method()) {
            'POST' => PermissionAction::Create,
            'PUT', 'PATCH' => PermissionAction::Edit,
            'DELETE' => PermissionAction::Delete,
            default => PermissionAction::View,
        };
    }
}
