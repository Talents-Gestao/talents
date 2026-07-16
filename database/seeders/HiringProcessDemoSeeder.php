<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\HiringProcessStage;
use App\Models\Company;
use App\Models\HiringProcess;
use App\Models\Module;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;

class HiringProcessDemoSeeder extends Seeder
{
    public function run(): void
    {
        $module = Module::query()->firstOrCreate(
            ['key' => Module::KEY_ACOMPANHAMENTO],
            [
                'name' => 'Acompanhamento',
                'description' => 'Acompanhamento visual das fases de contratação (processo operacional na Sólides).',
            ]
        );

        $plan = Plan::query()->where('slug', 'nr1-pro')->first()
            ?? Plan::query()->where('is_active', true)->first();

        if ($plan) {
            $plan->modules()->syncWithoutDetaching([$module->id]);
        }

        $admin = User::query()->where('email', 'admin@talents.local')->first();
        $stages = HiringProcessStage::ordered();

        $company = Company::query()->where('name', 'Empresa Demo')->first()
            ?? Company::query()->orderBy('id')->first();

        if ($company === null) {
            $this->command?->warn('Nenhuma empresa encontrada para seed de acompanhamento.');

            return;
        }

        $company->update(['acompanhamento_access' => true]);

        $samples = [
            ['Analista de RH', 0, 'Triagem inicial dos currículos recebidos.'],
            ['Desenvolvedor Full Stack', 0, null],
            ['Assistente Administrativo', 0, null],
            ['Coordenador Comercial', 1, 'Teste comportamental agendado.'],
            ['Analista Financeiro', 1, null],
            ['Gerente de Operações', 2, 'Entrevista presencial marcada para sexta.'],
            ['Designer UX', 2, null],
            ['Product Owner', 3, 'Aguardando disponibilidade do gestor.'],
            ['Engenheiro de Dados', 4, 'Visita opcional — empresa ainda a confirmar.'],
            ['Especialista em Compliance', 5, 'Documentação em andamento.'],
        ];

        foreach ($samples as [$title, $stageIndex, $notes]) {
            HiringProcess::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'title' => $title,
                ],
                [
                    'current_stage' => $stages[$stageIndex],
                    'notes' => $notes,
                    'updated_by' => $admin?->id,
                ]
            );
        }

        $other = Company::query()
            ->where('id', '!=', $company->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($other) {
            $other->update(['acompanhamento_access' => true]);

            HiringProcess::query()->updateOrCreate(
                [
                    'company_id' => $other->id,
                    'title' => 'Analista de Marketing',
                ],
                [
                    'current_stage' => HiringProcessStage::AnaliseCurriculo,
                    'notes' => 'Processo de demonstração da segunda empresa.',
                    'updated_by' => $admin?->id,
                ]
            );

            HiringProcess::query()->updateOrCreate(
                [
                    'company_id' => $other->id,
                    'title' => 'Supervisor de Produção',
                ],
                [
                    'current_stage' => HiringProcessStage::EntrevistaGestor,
                    'notes' => null,
                    'updated_by' => $admin?->id,
                ]
            );
        }

        $this->command?->info(
            'Acompanhamento: '.HiringProcess::query()->count().' processo(s); '
            .'empresa principal «'.$company->name.'» com acesso habilitado.'
        );
    }
}
