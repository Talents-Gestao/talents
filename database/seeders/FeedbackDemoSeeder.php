<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\FeedbackSessionStatus;
use App\Enums\FeedbackSignatureRole;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\Department;
use App\Models\FeedbackSession;
use App\Models\FeedbackSessionAnswer;
use App\Models\FeedbackSessionSignature;
use App\Models\FeedbackTemplate;
use App\Models\FeedbackTemplateQuestion;
use App\Models\Plan;
use App\Models\Position;
use App\Models\Subscription;
use App\Models\User;
use App\Support\WorkspaceManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class FeedbackDemoSeeder extends Seeder
{
    public function run(): void
    {
        $template = FeedbackTemplate::query()
            ->whereNull('company_id')
            ->where('is_default', true)
            ->first();

        if (! $template) {
            $this->command?->warn('FeedbackDemoSeeder: template padrão não encontrado. Execute FeedbackTemplateSeeder primeiro.');

            return;
        }

        $this->seedEmpresaDemo($template);
        $this->seedEmpresaInovacao($template);
    }

    private function seedEmpresaDemo(FeedbackTemplate $template): void
    {
        $company = Company::query()->where('cnpj', '00.000.000/0001-99')->first();
        if (! $company) {
            return;
        }

        $company->update(['feedbacks_access' => true, 'segment' => 'tecnologia']);

        $rh = User::query()->where('email', 'rh@empresa.local')->first();
        $deptOps = Department::query()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Operações'],
        );
        $deptAdm = Department::query()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Administrativo'],
        );
        $posAnalista = Position::query()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Analista'],
        );
        $posCoord = Position::query()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Coordenador'],
        );

        $leader = User::query()->firstOrCreate(
            ['email' => 'lider@empresa.local'],
            [
                'name' => 'Líder Demo',
                'password' => Hash::make('password'),
                'role' => UserRole::CompanyUser,
                'company_id' => $company->id,
            ],
        );

        app(WorkspaceManager::class)->syncLegacyUserColumns($leader);

        $employees = [
            [
                'email' => 'ana.costa@empresa-demo.local',
                'name' => 'Ana Costa',
                'department_id' => $deptOps->id,
                'position_id' => $posAnalista->id,
                'leader_user_id' => $leader->id,
            ],
            [
                'email' => 'bruno.lima@empresa-demo.local',
                'name' => 'Bruno Lima',
                'department_id' => $deptOps->id,
                'position_id' => $posAnalista->id,
                'leader_user_id' => $leader->id,
            ],
            [
                'email' => 'carla.mendes@empresa-demo.local',
                'name' => 'Carla Mendes',
                'department_id' => $deptAdm->id,
                'position_id' => $posCoord->id,
                'leader_user_id' => $rh?->id,
            ],
            [
                'email' => 'diego.souza@empresa-demo.local',
                'name' => 'Diego Souza',
                'department_id' => $deptOps->id,
                'position_id' => $posAnalista->id,
                'leader_user_id' => $leader->id,
            ],
        ];

        $employeeModels = [];
        foreach ($employees as $row) {
            $employeeModels[$row['email']] = CompanyEmployee::query()->firstOrCreate(
                ['company_id' => $company->id, 'email' => $row['email']],
                [
                    'name' => $row['name'],
                    'phone' => '(11) 99999-0000',
                    'department_id' => $row['department_id'],
                    'position_id' => $row['position_id'],
                    'leader_user_id' => $row['leader_user_id'],
                    'is_active' => true,
                    'notes' => 'Colaborador de demonstração — Feedbacks internos.',
                ],
            );
        }

        if (FeedbackSession::query()->where('company_id', $company->id)->exists()) {
            return;
        }

        $q = fn (string $key) => $this->questionId($template, $key);

        // Concluído — Ana (líder Demo)
        $completed = FeedbackSession::create([
            'company_id' => $company->id,
            'feedback_template_id' => $template->id,
            'company_employee_id' => $employeeModels['ana.costa@empresa-demo.local']->id,
            'leader_user_id' => $leader->id,
            'created_by_user_id' => $rh?->id,
            'title' => 'Feedback — Ana Costa (Q2/2026)',
            'status' => FeedbackSessionStatus::Completed,
            'scheduled_at' => now()->subDays(12),
            'next_alignment_at' => now()->addMonths(6),
            'completed_at' => now()->subDays(10),
        ]);

        $this->answer($completed, $q('termometro_nivel'), 'muito_bom');
        $this->answer($completed, $q('termometro_comentario'), 'Momento positivo na equipe; sinto evolução nas entregas e no relacionamento com a liderança.');
        $this->answer($completed, $q('inicio_felicidade'), 'Sim, de forma geral estou satisfeita com equilíbrio entre vida pessoal e trabalho.');
        $this->answer($completed, $q('conquistas_lista'), ['Liderou projeto de automação', 'Reduziu retrabalho em 15%', 'Mentoria de estagiários']);
        $this->answer($completed, $q('perc_comportamento'), 'acima');
        $this->answer($completed, $q('perc_desempenho'), 'dentro');
        $this->answer($completed, $q('perc_potencial'), 'sim');
        $this->answer($completed, $q('acoes_ccm'), [
            'start' => 'Participar do programa de liderança técnica.',
            'continue' => 'Comunicação proativa com stakeholders.',
            'improve' => 'Documentação de processos internos.',
            'stop' => 'Acumular tarefas sem priorização.',
        ]);
        $this->answer($completed, $q('acoes_tabela'), [
            ['action' => 'Concluir curso de gestão de projetos', 'responsible' => 'Ana', 'deadline' => '30/09/2026'],
            ['action' => 'Apresentar retrospectiva da squad', 'responsible' => 'Líder Demo', 'deadline' => '15/08/2026'],
        ]);

        $this->signedPair($completed, $employeeModels['ana.costa@empresa-demo.local'], $leader);

        // Aguardando assinatura — Bruno
        $awaiting = FeedbackSession::create([
            'company_id' => $company->id,
            'feedback_template_id' => $template->id,
            'company_employee_id' => $employeeModels['bruno.lima@empresa-demo.local']->id,
            'leader_user_id' => $leader->id,
            'created_by_user_id' => $leader->id,
            'title' => 'Feedback — Bruno Lima (Q2/2026)',
            'status' => FeedbackSessionStatus::AwaitingSignatures,
            'scheduled_at' => now()->subDays(3),
            'next_alignment_at' => now()->addMonths(6),
        ]);

        $this->answer($awaiting, $q('termometro_nivel'), 'bom');
        $this->answer($awaiting, $q('termometro_comentario'), 'Bom momento, com espaço para maior clareza de prioridades.');
        $this->answer($awaiting, $q('perc_comportamento'), 'dentro');
        $this->answer($awaiting, $q('perc_desempenho'), 'dentro');

        FeedbackSessionSignature::create([
            'feedback_session_id' => $awaiting->id,
            'role' => FeedbackSignatureRole::Employee,
            'signer_name' => 'Bruno Lima',
            'signer_email' => 'bruno.lima@empresa-demo.local',
            'token' => (string) Str::uuid(),
            'sent_at' => now()->subDay(),
        ]);
        FeedbackSessionSignature::create([
            'feedback_session_id' => $awaiting->id,
            'role' => FeedbackSignatureRole::Leader,
            'signer_name' => 'Líder Demo',
            'signer_email' => 'lider@empresa.local',
            'token' => (string) Str::uuid(),
            'sent_at' => now()->subDay(),
        ]);

        // Em preenchimento — Carla (RH como líder)
        $inProgress = FeedbackSession::create([
            'company_id' => $company->id,
            'feedback_template_id' => $template->id,
            'company_employee_id' => $employeeModels['carla.mendes@empresa-demo.local']->id,
            'leader_user_id' => $rh?->id ?? $leader->id,
            'created_by_user_id' => $rh?->id,
            'title' => 'Feedback — Carla Mendes (rascunho)',
            'status' => FeedbackSessionStatus::InProgress,
            'scheduled_at' => now()->addDays(5),
            'next_alignment_at' => now()->addMonths(6),
        ]);

        $this->answer($inProgress, $q('termometro_nivel'), 'regular');
        $this->answer($inProgress, $q('inicio_aproveitamento'), 'Gostaria de atuar mais em projetos estratégicos.');

        // Concluído anterior — Diego (para gráfico de linha)
        $older = FeedbackSession::create([
            'company_id' => $company->id,
            'feedback_template_id' => $template->id,
            'company_employee_id' => $employeeModels['diego.souza@empresa-demo.local']->id,
            'leader_user_id' => $leader->id,
            'created_by_user_id' => $leader->id,
            'title' => 'Feedback — Diego Souza (Q1/2026)',
            'status' => FeedbackSessionStatus::Completed,
            'scheduled_at' => now()->subMonths(2),
            'next_alignment_at' => now()->addMonths(4),
            'completed_at' => now()->subMonths(2)->addDays(2),
        ]);

        $this->answer($older, $q('termometro_nivel'), 'excelente');
        $this->answer($older, $q('perc_comportamento'), 'acima');
        $this->answer($older, $q('perc_desempenho'), 'acima');
        $this->answer($older, $q('conquistas_lista'), ['Meta de vendas internas superada', 'Feedback 360 positivo']);
        $this->signedPair($older, $employeeModels['diego.souza@empresa-demo.local'], $leader);
    }

    private function seedEmpresaInovacao(FeedbackTemplate $template): void
    {
        $plan = Plan::query()->where('slug', 'nr1-pro')->first();
        if (! $plan) {
            return;
        }

        $company = Company::query()->firstOrCreate(
            ['cnpj' => '11.222.333/0001-44'],
            [
                'name' => 'Inovação Plus',
                'legal_name' => 'Inovação Plus LTDA',
                'segment' => 'servicos',
                'employee_count_estimate' => 45,
                'is_active' => true,
                'feedbacks_access' => true,
                'complaints_public_token' => (string) Str::uuid(),
            ],
        );

        $company->update(['feedbacks_access' => true]);

        Subscription::query()->firstOrCreate(
            ['company_id' => $company->id, 'plan_id' => $plan->id],
            [
                'starts_at' => now(),
                'ends_at' => now()->addYear(),
                'status' => 'active',
            ],
        );

        $leader = User::query()->firstOrCreate(
            ['email' => 'rh@inovacao.local'],
            [
                'name' => 'RH Inovação Plus',
                'password' => Hash::make('password'),
                'role' => UserRole::CompanyAdmin,
                'company_id' => $company->id,
            ],
        );

        app(WorkspaceManager::class)->syncLegacyUserColumns($leader);

        $employee = CompanyEmployee::query()->firstOrCreate(
            ['company_id' => $company->id, 'email' => 'mariana@inovacao.local'],
            [
                'name' => 'Mariana Rocha',
                'leader_user_id' => $leader->id,
                'is_active' => true,
            ],
        );

        if (FeedbackSession::query()->where('company_id', $company->id)->exists()) {
            return;
        }

        $session = FeedbackSession::create([
            'company_id' => $company->id,
            'feedback_template_id' => $template->id,
            'company_employee_id' => $employee->id,
            'leader_user_id' => $leader->id,
            'created_by_user_id' => $leader->id,
            'title' => 'Feedback — Mariana Rocha',
            'status' => FeedbackSessionStatus::Completed,
            'scheduled_at' => now()->subDays(20),
            'next_alignment_at' => now()->addMonths(5),
            'completed_at' => now()->subDays(18),
        ]);

        $this->answer($session, $this->questionId($template, 'termometro_nivel'), 'bom');
        $this->answer($session, $this->questionId($template, 'perc_comportamento'), 'dentro');
        $this->answer($session, $this->questionId($template, 'perc_desempenho'), 'abaixo');
        $this->signedPair($session, $employee, $leader);
    }

    private function questionId(FeedbackTemplate $template, string $key): ?int
    {
        return FeedbackTemplateQuestion::query()
            ->where('key', $key)
            ->whereHas('section', fn ($q) => $q->where('feedback_template_id', $template->id))
            ->value('id');
    }

    private function answer(FeedbackSession $session, ?int $questionId, string|array $value): void
    {
        if (! $questionId) {
            return;
        }

        FeedbackSessionAnswer::updateOrCreate(
            [
                'feedback_session_id' => $session->id,
                'feedback_template_question_id' => $questionId,
            ],
            [
                'value_text' => is_string($value) ? $value : null,
                'value_json' => is_array($value) ? $value : null,
            ],
        );
    }

    private function signedPair(FeedbackSession $session, CompanyEmployee $employee, User $leader): void
    {
        FeedbackSessionSignature::create([
            'feedback_session_id' => $session->id,
            'role' => FeedbackSignatureRole::Employee,
            'signer_name' => $employee->name,
            'signer_email' => $employee->email,
            'token' => (string) Str::uuid(),
            'sent_at' => $session->completed_at,
            'signed_at' => $session->completed_at,
        ]);

        FeedbackSessionSignature::create([
            'feedback_session_id' => $session->id,
            'role' => FeedbackSignatureRole::Leader,
            'signer_name' => $leader->name,
            'signer_email' => $leader->email,
            'token' => (string) Str::uuid(),
            'sent_at' => $session->completed_at,
            'signed_at' => $session->completed_at,
        ]);
    }
}
