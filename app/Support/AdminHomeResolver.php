<?php

namespace App\Support;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

class AdminHomeResolver
{
    /**
     * Primeira rota index do admin por módulo (ordem do menu / enum).
     *
     * @var array<string, string>
     */
    private const MODULE_HOME_ROUTES = [
        'dashboard' => 'admin.dashboard',
        'landing_interest' => 'admin.landing-interest.index',
        'companies' => 'admin.companies.index',
        'plans' => 'admin.plans.index',
        'survey_templates' => 'admin.survey-templates.index',
        'methodology' => 'admin.metodologia.index',
        'strategic_calendar' => 'admin.strategic-calendar.index',
        'tarefas' => 'admin.tarefas.quadros.index',
        'comercial' => 'admin.comercial.dashboard',
        'empresa_talents' => 'admin.empresa-talents.edit',
        'solides' => 'admin.solides.curriculos.index',
        'settings' => 'admin.settings.edit',
        'training' => 'admin.training.index',
        'equipe' => 'admin.users.index',
        'entrevistas' => 'admin.entrevistas.index',
    ];

    public function routeNameFor(User $user): ?string
    {
        if (! $user->isSuperAdmin()) {
            return null;
        }

        if ($user->isOwner()) {
            return 'admin.dashboard';
        }

        foreach (AdminPermissionModule::all() as $module) {
            if ($user->canAccessAdmin($module, PermissionAction::View)) {
                return self::MODULE_HOME_ROUTES[$module->value] ?? null;
            }
        }

        return null;
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
