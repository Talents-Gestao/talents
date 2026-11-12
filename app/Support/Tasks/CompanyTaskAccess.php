<?php

namespace App\Support\Tasks;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Enums\UserRole;
use App\Enums\WorkspaceType;
use App\Models\Company;
use App\Models\TaskBoard;
use App\Models\TaskCard;
use App\Models\User;
use App\Models\UserWorkspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Leitura de quem da empresa cliente acessa o módulo Tarefas (e um quadro) no portal, com as mesmas regras de
 * User::canAccess, TaskBoardPolicy::view e BoardController (client). Não altera nada e não envia e-mail.
 *
 * - Módulo: Company::hasTasksEnabled (override `tasks_access` na empresa ou chave `tarefas` no plano ativo).
 * - company_admin ativo: todos os quadros da empresa (e os internos com cartões partilhados).
 * - company_user ativo: precisa de tarefas:view e ser membro do quadro ou de um cartão visível do quadro.
 * - Sem senha definida (password_set_at nulo) o usuário ainda não entra: convite pendente.
 */
final class CompanyTaskAccess
{
    /**
     * @return array<string, mixed>
     */
    public static function for(Company $company, ?TaskBoard $board = null): array
    {
        $module = self::moduleStatus($company);

        $workspaces = UserWorkspace::query()
            ->where('workspace_type', WorkspaceType::Company)
            ->where('company_id', $company->id)
            ->whereIn('role', [UserRole::CompanyAdmin->value, UserRole::CompanyUser->value])
            ->with([
                'user:id,name,email,is_active,password_set_at',
                'permissions' => fn ($q) => $q->where('module', PermissionModule::Tarefas->value),
            ])
            ->get()
            ->filter(fn (UserWorkspace $workspace) => $workspace->user !== null);

        $emails = $workspaces->map(fn (UserWorkspace $w) => $w->user->email)->all();
        $lastInvites = $emails === []
            ? collect()
            : DB::table('password_reset_tokens')->whereIn('email', $emails)->pluck('created_at', 'email');

        $boardMemberIds = self::boardMemberIds($board, $company);
        $registrationAdminId = $company->registrationAdmin()?->id;

        $users = $workspaces
            ->map(function (UserWorkspace $workspace) use ($module, $board, $boardMemberIds, $lastInvites, $registrationAdminId): array {
                $user = $workspace->user;
                $isAdmin = $workspace->role === UserRole::CompanyAdmin;
                $status = match (true) {
                    ! $user->is_active || ! $workspace->is_active => 'inactive',
                    $user->password_set_at === null => 'pending',
                    default => 'active',
                };
                $level = $isAdmin ? 'edit' : self::tasksLevel($workspace);
                [$boardAccess, $reason] = self::boardAccess($module['enabled'], $status, $isAdmin, $level, $board, $boardMemberIds, (int) $user->id);
                $lastInvite = $lastInvites[$user->email] ?? null;

                return [
                    'id' => (int) $user->id,
                    'name' => (string) $user->name,
                    'email' => (string) $user->email,
                    'role' => $workspace->role->value,
                    'role_label' => $isAdmin ? 'Administrador da empresa' : 'Usuário da empresa',
                    'tasks_level' => $level,
                    'tasks_level_label' => ['none' => 'Nenhum', 'view' => 'Visualizar', 'edit' => 'Editar'][$level],
                    'status' => $status,
                    'status_label' => ['active' => 'Ativo', 'pending' => 'Convite pendente', 'inactive' => 'Inativo'][$status],
                    'last_invite_at' => $lastInvite ? Carbon::parse($lastInvite)->toIso8601String() : null,
                    'is_registration_contact' => $registrationAdminId !== null && (int) $user->id === (int) $registrationAdminId,
                    'board_access' => $boardAccess,
                    'board_access_reason' => $reason,
                ];
            })
            ->sortBy([['board_access', 'desc'], ['name', 'asc']])
            ->values();

        $pending = $users->where('status', 'pending')->values();

        return [
            'company' => ['id' => (int) $company->id, 'name' => (string) $company->name],
            'board' => $board ? ['id' => (int) $board->id, 'name' => (string) $board->name, 'is_internal' => $board->company_id === null] : null,
            'module' => $module,
            'users' => $users->all(),
            'with_access_count' => $users->where('board_access', true)->count(),
            'pending_count' => $pending->count(),
            'registration_contact' => $users->firstWhere('is_registration_contact', true)['email'] ?? null,
            // Ativar processo e partilhar cartões não disparam e-mail (InstantiateProcessTemplateForCompany, ShareBoardCardsWithCompany).
            'sends_email' => false,
            'pending_emails' => $pending->pluck('email')->all(),
        ];
    }

    /**
     * @return array{enabled: bool, rule: string, label: string}
     */
    private static function moduleStatus(Company $company): array
    {
        $enabled = $company->hasTasksEnabled();
        $rule = match ($company->tasks_access) {
            true => 'company_on',
            false => 'company_off',
            default => $enabled ? 'plan_on' : 'plan_off',
        };

        return [
            'enabled' => $enabled,
            'rule' => $rule,
            'label' => [
                'company_on' => 'Habilitado no cadastro da empresa (acesso ao módulo ligado)',
                'company_off' => 'Desabilitado no cadastro da empresa (acesso ao módulo desligado)',
                'plan_on' => 'Habilitado pelo plano da assinatura ativa',
                'plan_off' => 'Desabilitado: o plano ativo não inclui Tarefas e a empresa não tem o acesso ligado',
            ][$rule],
        ];
    }

    private static function tasksLevel(UserWorkspace $workspace): string
    {
        $actions = $workspace->permissions
            ->map(fn ($permission) => $permission->action instanceof PermissionAction ? $permission->action->value : (string) $permission->action)
            ->all();

        if (in_array(PermissionAction::Edit->value, $actions, true)) {
            return 'edit';
        }

        return in_array(PermissionAction::View->value, $actions, true) ? 'view' : 'none';
    }

    /**
     * Usuários que são membros do quadro ou de um cartão dele que a empresa vê (quadro interno: cartões ainda
     * sem empresa ou já desta empresa, que são os que a partilha vincula).
     *
     * @return array<int, true>
     */
    private static function boardMemberIds(?TaskBoard $board, Company $company): array
    {
        if ($board === null) {
            return [];
        }

        $ids = $board->members()->pluck('users.id')->all();

        $cards = TaskCard::query()
            ->whereHas('list', fn ($q) => $q->where('board_id', $board->id)->where('is_archived', false))
            ->where('is_archived', false);

        if ($board->company_id === null) {
            $cards->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $company->id));
        } else {
            $cards->visibleToCompany((int) $company->id);
        }

        $cardMemberIds = DB::table('task_card_members')
            ->whereIn('task_card_id', $cards->select('id'))
            ->pluck('user_id')
            ->all();

        return array_fill_keys(array_map('intval', [...$ids, ...$cardMemberIds]), true);
    }

    /**
     * @param  array<int, true>  $boardMemberIds
     * @return array{0: bool, 1: string}
     */
    private static function boardAccess(bool $moduleEnabled, string $status, bool $isAdmin, string $level, ?TaskBoard $board, array $boardMemberIds, int $userId): array
    {
        if (! $moduleEnabled) {
            return [false, 'Módulo Tarefas desabilitado para a empresa'];
        }
        if ($status === 'inactive') {
            return [false, 'Usuário inativo'];
        }
        if ($status === 'pending') {
            return [false, 'Ainda não aceitou o convite (sem senha definida)'];
        }
        if ($isAdmin) {
            return [true, 'Administrador da empresa vê todos os quadros da empresa'];
        }
        if ($level === 'none') {
            return [false, 'Sem permissão de Tarefas'];
        }
        if ($board === null) {
            return [false, 'Vê só quadros ou cartões em que for adicionado como membro'];
        }
        if (isset($boardMemberIds[$userId])) {
            return [true, 'Membro do quadro ou de um cartão do quadro'];
        }

        return [false, 'Não é membro do quadro nem de um cartão do quadro'];
    }
}
