<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Enums\TaskBoardMemberRole;
use App\Enums\TaskCardRecurrence;
use App\Enums\UserRole;
use App\Enums\WorkspaceType;
use App\Models\Company;
use App\Models\TaskBoard;
use App\Models\TaskCard;
use App\Models\TaskChecklist;
use App\Models\TaskChecklistItem;
use App\Models\TaskComment;
use App\Models\TaskLabel;
use App\Models\TaskList;
use App\Models\TaskProcessTemplate;
use App\Models\TaskTemplateCard;
use App\Models\TaskTemplateList;
use App\Models\User;
use App\Models\UserPermission;
use App\Models\UserWorkspace;
use App\Support\WorkspaceManager;
use Illuminate\Database\Seeder;

/**
 * Cenários fictícios para o módulo Tarefas (admin + cliente).
 * Idempotente: quadros/cards identificados pelo prefixo DEMO-.
 */
class TasksDemoSeeder extends Seeder
{
    private const COMPANY_BOARD = 'DEMO: Operações RH — Empresa Demo';

    private const INTERNAL_BOARD = 'Quadro Único de Tarefas';

    private const PROCESS_SLUG = 'demo-onboarding-nr1';

    public function run(): void
    {
        $company = Company::query()->where('name', 'Empresa Demo')->first();
        $admin = User::query()->where('email', 'admin@talents.local')->first();
        $rh = User::query()->where('email', 'rh@empresa.local')->first();
        $lider = User::query()->where('email', 'lider@empresa.local')->first();

        if (! $company || ! $admin) {
            $this->command?->warn('TasksDemoSeeder: execute TalentsSeeder antes (Empresa Demo + admin).');

            return;
        }

        if (! $lider) {
            $this->command?->warn('TasksDemoSeeder: lider@empresa.local ausente — execute FeedbackDemoSeeder antes para permissões do líder.');
        } else {
            $this->ensureLeaderCanViewTasks($lider, $company);
        }

        $process = $this->seedProcessTemplate();
        $companyBoard = $this->seedCompanyBoard($company, $admin, $rh, $lider);
        $this->seedInternalBoardCards($company, $admin, $rh, $lider);
        $this->seedArchivedSample($companyBoard, $company, $admin);

        $this->command?->info('TasksDemoSeeder: cenários de tarefas prontos.');
        $this->command?->line('  Login admin: admin@talents.local / password → /admin/tarefas');
        $this->command?->line('  Login RH:    rh@empresa.local / password → /client/tarefas');
        $this->command?->line('  Login líder: lider@empresa.local / password → /client/tarefas (1 card atribuído)');
        $this->command?->line('  Processo demo: '.$process->name.' (slug '.$process->slug.')');
    }

    private function ensureLeaderCanViewTasks(User $lider, Company $company): void
    {
        app(WorkspaceManager::class)->syncLegacyUserColumns($lider);

        $workspace = $lider->workspaces()
            ->where('company_id', $company->id)
            ->first();

        if (! $workspace) {
            $workspace = UserWorkspace::query()->create([
                'user_id' => $lider->id,
                'workspace_type' => WorkspaceType::Company,
                'company_id' => $company->id,
                'role' => UserRole::CompanyUser,
                'is_owner' => false,
                'is_active' => true,
            ]);
            app(WorkspaceManager::class)->syncLegacyUserColumns($lider);
        }

        foreach ([PermissionAction::View, PermissionAction::Edit] as $action) {
            UserPermission::query()->firstOrCreate(
                [
                    'user_workspace_id' => $workspace->id,
                    'module' => PermissionModule::Tarefas->value,
                    'action' => $action->value,
                ],
            );
        }
    }

    private function seedProcessTemplate(): TaskProcessTemplate
    {
        $template = TaskProcessTemplate::query()->firstOrCreate(
            ['slug' => self::PROCESS_SLUG],
            [
                'name' => 'DEMO: Onboarding NR-1',
                'description' => 'Modelo de processo para testes — ativar numa empresa em Admin → Tarefas → Processos.',
                'cover_color' => '#0F766E',
                'is_active' => true,
            ],
        );

        if ($template->lists()->exists()) {
            return $template;
        }

        $todo = TaskTemplateList::query()->create([
            'process_template_id' => $template->id,
            'name' => 'Planejar',
            'position' => 1000,
            'default_visibility' => 'company',
            'allow_company_drop_in' => true,
        ]);
        $doing = TaskTemplateList::query()->create([
            'process_template_id' => $template->id,
            'name' => 'Executar',
            'position' => 2000,
            'default_visibility' => 'company',
            'allow_company_drop_in' => true,
        ]);
        $done = TaskTemplateList::query()->create([
            'process_template_id' => $template->id,
            'name' => 'Validar',
            'position' => 3000,
            'default_visibility' => 'company',
            'allow_company_drop_in' => true,
        ]);

        TaskTemplateCard::query()->create([
            'template_list_id' => $todo->id,
            'title' => 'DEMO-TPL: Kickoff com RH',
            'description' => 'Alinhar escopo da pesquisa psicossocial.',
            'position' => 1000,
            'default_visibility' => 'company',
            'default_due_offset_days' => 3,
        ]);
        TaskTemplateCard::query()->create([
            'template_list_id' => $doing->id,
            'title' => 'DEMO-TPL: Coletar mapa de setores',
            'description' => 'Listar departamentos e líderes envolvidos.',
            'position' => 1000,
            'default_visibility' => 'company',
            'default_due_offset_days' => 7,
        ]);
        TaskTemplateCard::query()->create([
            'template_list_id' => $done->id,
            'title' => 'DEMO-TPL: Aprovar cronograma',
            'description' => 'Fechar datas de abertura e fechamento da pesquisa.',
            'position' => 1000,
            'default_visibility' => 'company',
            'default_due_offset_days' => 10,
        ]);

        return $template;
    }

    private function seedCompanyBoard(Company $company, User $admin, ?User $rh, ?User $lider): TaskBoard
    {
        $board = TaskBoard::query()->firstOrCreate(
            [
                'company_id' => $company->id,
                'name' => self::COMPANY_BOARD,
            ],
            [
                'description' => 'Quadro da Empresa Demo para testes de visibilidade cliente/admin.',
                'cover_color' => '#1D4ED8',
                'is_archived' => false,
                'created_by_user_id' => $admin->id,
            ],
        );

        $lists = $this->ensureStandardLists($board);
        $labels = $this->ensureLabels($board, [
            ['name' => 'Urgente', 'color' => '#DC2626', 'position' => 1000],
            ['name' => 'NR-1', 'color' => '#7C3AED', 'position' => 2000],
            ['name' => 'RH', 'color' => '#059669', 'position' => 3000],
        ]);

        if ($rh && ! $board->hasMember($rh->id)) {
            $board->members()->attach($rh->id, ['role' => TaskBoardMemberRole::Owner->value]);
        }
        if ($lider && ! $board->hasMember($lider->id)) {
            // Líder NÃO é membro do quadro — só recebe cards atribuídos (cenário de filtro).
        }

        $todo = $lists['A fazer'];
        $doing = $lists['Em andamento'];
        $done = $lists['Concluído'];

        $cardKickoff = $this->upsertCard($todo, $company, $admin, [
            'title' => 'DEMO-TASK: Agendar kickoff NR-1',
            'description' => 'Reunião inicial com RH e lideranças. Visível no painel do cliente.',
            'position' => 1000,
            'visibility' => 'company',
            'due_date' => now()->addDays(2)->toDateString(),
            'start_date' => now()->toDateString(),
            'cover_color' => '#F59E0B',
        ]);
        $cardKickoff->labels()->syncWithoutDetaching([$labels['Urgente']->id, $labels['NR-1']->id]);
        if ($rh) {
            $cardKickoff->members()->syncWithoutDetaching([$rh->id]);
        }

        $cardMap = $this->upsertCard($doing, $company, $admin, [
            'title' => 'DEMO-TASK: Mapear setores e riscos',
            'description' => 'Em andamento — checklist parcial para testar progresso.',
            'position' => 1000,
            'visibility' => 'inherit',
            'due_date' => now()->addDays(5)->toDateString(),
            'cover_color' => '#3B82F6',
        ]);
        $cardMap->labels()->syncWithoutDetaching([$labels['NR-1']->id, $labels['RH']->id]);
        $this->ensureChecklist($cardMap, 'Passos do mapeamento', [
            ['text' => 'Listar departamentos', 'is_completed' => true, 'position' => 1000],
            ['text' => 'Identificar líderes', 'is_completed' => true, 'position' => 2000],
            ['text' => 'Classificar riscos preliminares', 'is_completed' => false, 'position' => 3000],
        ]);
        if ($rh) {
            TaskComment::query()->firstOrCreate(
                [
                    'task_card_id' => $cardMap->id,
                    'user_id' => $rh->id,
                    'body' => 'DEMO: Operações e Administrativo já mapeados. Falta comercial.',
                ],
                ['mentioned_user_ids' => $lider ? [$lider->id] : null],
            );
        }

        $cardLeaderOnly = $this->upsertCard($todo, $company, $admin, [
            'title' => 'DEMO-TASK: Feedback 1:1 com Ana Costa',
            'description' => 'Card atribuído só ao líder — company_user vê este e não os demais (sem membership no quadro).',
            'position' => 2000,
            'visibility' => 'company',
            'due_date' => now()->addDays(4)->toDateString(),
            'cover_color' => '#10B981',
        ]);
        $cardLeaderOnly->labels()->syncWithoutDetaching([$labels['RH']->id]);
        if ($lider) {
            $cardLeaderOnly->members()->syncWithoutDetaching([$lider->id]);
        }

        $cardWeekly = $this->upsertCard($todo, $company, $admin, [
            'title' => 'DEMO-TASK: Revisar indicadores semanais',
            'description' => 'Tarefa recorrente semanal para testar recurrence no calendário/estratégico.',
            'position' => 3000,
            'visibility' => 'company',
            'due_date' => now()->addDays(1)->toDateString(),
            'recurrence' => TaskCardRecurrence::Weekly->value,
            'recurrence_ends_on' => now()->addMonths(2)->toDateString(),
            'cover_color' => '#8B5CF6',
        ]);

        $cardInternalClientHidden = $this->upsertCard($todo, $company, $admin, [
            'title' => 'DEMO-TASK: Nota interna Talents (oculta ao cliente)',
            'description' => 'visibility=internal — RH Demo NÃO deve ver este card no /client.',
            'position' => 4000,
            'visibility' => 'internal',
            'due_date' => now()->addDays(7)->toDateString(),
            'cover_color' => '#64748B',
        ]);

        $cardDone = $this->upsertCard($done, $company, $admin, [
            'title' => 'DEMO-TASK: Enviar comunicação de abertura',
            'description' => 'Concluída — testa completed_at e lista Concluído.',
            'position' => 1000,
            'visibility' => 'company',
            'completed_at' => now()->subDay()->toDateTimeString(),
            'due_date' => now()->subDays(2)->toDateString(),
            'cover_color' => '#22C55E',
        ]);
        $cardDone->labels()->syncWithoutDetaching([$labels['RH']->id]);

        // Referência explícita a variáveis usadas só para cenários (evita dead-code warnings em IDEs).
        unset($cardWeekly, $cardInternalClientHidden);

        return $board;
    }

    private function seedInternalBoardCards(Company $company, User $admin, ?User $rh, ?User $lider): void
    {
        $board = TaskBoard::query()
            ->whereNull('company_id')
            ->where('name', self::INTERNAL_BOARD)
            ->where('is_archived', false)
            ->orderBy('id')
            ->first();

        if (! $board) {
            $board = TaskBoard::query()->create([
                'company_id' => null,
                'name' => self::INTERNAL_BOARD,
                'description' => 'Quadro central para todas as tarefas e empresas.',
                'cover_color' => null,
                'is_archived' => false,
                'created_by_user_id' => $admin->id,
            ]);
        }

        $lists = $this->ensureStandardLists($board);
        $todo = $lists['A fazer'];
        $doing = $lists['Em andamento'];

        // Lista só Talents (cliente não vê a lista, mas card com company_id pode aparecer via regras de visibilidade).
        $internalList = TaskList::query()->firstOrCreate(
            [
                'board_id' => $board->id,
                'name' => 'DEMO: Só Talents',
            ],
            [
                'position' => 4000,
                'visibility' => 'internal',
                'allow_company_drop_in' => false,
                'is_archived' => false,
                'color' => '#475569',
            ],
        );

        $this->upsertCard($todo, $company, $admin, [
            'title' => 'DEMO-TASK: Follow-up comercial → RH (quadro interno)',
            'description' => 'Card no Quadro Único com company_id da Empresa Demo — admin vê; cliente pode ver se visibility=company.',
            'position' => 5000,
            'visibility' => 'company',
            'due_date' => now()->addDays(3)->toDateString(),
            'cover_color' => '#EA580C',
        ]);

        $this->upsertCard($doing, $company, $admin, [
            'title' => 'DEMO-TASK: Preparar relatório parcial (interno/empresa)',
            'description' => 'Em andamento no quadro interno Talents.',
            'position' => 5000,
            'visibility' => 'inherit',
            'due_date' => now()->addDays(6)->toDateString(),
        ]);

        $this->upsertCard($internalList, $company, $admin, [
            'title' => 'DEMO-TASK: Briefing interno da consultoria',
            'description' => 'Lista visibility=internal + card company — testa regra TaskCardVisibility.',
            'position' => 1000,
            'visibility' => 'company',
            'due_date' => now()->addDays(8)->toDateString(),
            'cover_color' => '#0EA5E9',
        ]);

        $this->upsertCard($internalList, null, $admin, [
            'title' => 'DEMO-TASK: Backlog interno (sem empresa)',
            'description' => 'Sem company_id — não aparece no painel da Empresa Demo.',
            'position' => 2000,
            'visibility' => 'internal',
            'due_date' => now()->addDays(14)->toDateString(),
        ]);

        unset($rh, $lider);
    }

    private function seedArchivedSample(TaskBoard $board, Company $company, User $admin): void
    {
        $todo = $board->lists()->where('name', 'A fazer')->first();
        if (! $todo) {
            return;
        }

        $this->upsertCard($todo, $company, $admin, [
            'title' => 'DEMO-TASK: Card arquivado (só com ver_arquivados)',
            'description' => 'is_archived=true — oculto por padrão no board.',
            'position' => 9000,
            'visibility' => 'company',
            'is_archived' => true,
            'due_date' => now()->subDays(10)->toDateString(),
        ]);
    }

    /**
     * @return array<string, TaskList>
     */
    private function ensureStandardLists(TaskBoard $board): array
    {
        $defs = [
            ['name' => 'A fazer', 'position' => 1000],
            ['name' => 'Em andamento', 'position' => 2000],
            ['name' => 'Concluído', 'position' => 3000],
        ];

        $out = [];
        foreach ($defs as $def) {
            $out[$def['name']] = TaskList::query()->firstOrCreate(
                [
                    'board_id' => $board->id,
                    'name' => $def['name'],
                ],
                [
                    'position' => $def['position'],
                    'visibility' => 'company',
                    'allow_company_drop_in' => true,
                    'is_archived' => false,
                ],
            );
        }

        return $out;
    }

    /**
     * @param  list<array{name: string, color: string, position: float}>  $defs
     * @return array<string, TaskLabel>
     */
    private function ensureLabels(TaskBoard $board, array $defs): array
    {
        $out = [];
        foreach ($defs as $def) {
            $out[$def['name']] = TaskLabel::query()->firstOrCreate(
                [
                    'board_id' => $board->id,
                    'name' => $def['name'],
                ],
                [
                    'color' => $def['color'],
                    'position' => $def['position'],
                ],
            );
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function upsertCard(TaskList $list, ?Company $company, User $creator, array $attrs): TaskCard
    {
        $title = (string) $attrs['title'];

        $card = TaskCard::query()->firstOrCreate(
            [
                'list_id' => $list->id,
                'title' => $title,
            ],
            array_merge([
                'company_id' => $company?->id,
                'description' => null,
                'position' => 1000,
                'visibility' => 'company',
                'is_archived' => false,
                'created_by_user_id' => $creator->id,
            ], $attrs),
        );

        // Atualiza campos mutáveis em re-runs (mantém idempotência sem duplicar).
        $card->fill(array_merge(
            ['company_id' => $company?->id],
            collect($attrs)->except(['title'])->all(),
        ));
        $card->save();

        return $card->fresh();
    }

    /**
     * @param  list<array{text: string, is_completed: bool, position: float}>  $items
     */
    private function ensureChecklist(TaskCard $card, string $name, array $items): void
    {
        $checklist = TaskChecklist::query()->firstOrCreate(
            [
                'task_card_id' => $card->id,
                'name' => $name,
            ],
            [
                'position' => 1000,
                'is_completed' => false,
            ],
        );

        foreach ($items as $item) {
            TaskChecklistItem::query()->firstOrCreate(
                [
                    'task_checklist_id' => $checklist->id,
                    'text' => $item['text'],
                ],
                [
                    'position' => $item['position'],
                    'is_completed' => $item['is_completed'],
                ],
            );
        }

        $allDone = $checklist->items()->where('is_completed', false)->doesntExist()
            && $checklist->items()->exists();
        $checklist->update(['is_completed' => $allDone]);
    }
}
