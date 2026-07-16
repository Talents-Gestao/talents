<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ExitInterviewStatus;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\ExitInterview;
use App\Models\Module;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ExitInterviewDemoSeeder extends Seeder
{
    public function run(): void
    {
        $module = Module::query()->firstOrCreate(
            ['key' => Module::KEY_DESLIGAMENTO],
            [
                'name' => 'Pesquisa de Desligamento',
                'description' => 'Roteiro de entrevista de desligamento.',
            ]
        );

        $plan = Plan::query()->where('slug', 'nr1-pro')->first()
            ?? Plan::query()->where('is_active', true)->first();

        if ($plan) {
            $plan->modules()->syncWithoutDetaching([$module->id]);
        }

        $admin = User::query()->where('email', 'admin@talents.local')->first();

        $company = Company::query()->where('name', 'Empresa Demo')->first()
            ?? Company::query()->orderBy('id')->first();

        if ($company === null) {
            $this->command?->warn('Nenhuma empresa encontrada para seed de desligamento.');

            return;
        }

        $company->update(['desligamento_access' => true]);

        $samples = [
            [
                'name' => 'Ana Paula Ferreira',
                'email' => 'ana.ferreira@demo.local',
                'date' => '2026-06-10',
                'status' => ExitInterviewStatus::Completed,
                'answers' => [
                    'q1' => 'Experiência positiva no geral, com bom clima de equipe.',
                    'q4' => 'Oportunidade externa com crescimento de carreira.',
                    'q9' => 'Relação saudável com a liderança.',
                ],
                'notes' => [
                    'main_reasons' => 'Proposta salarial e cargo mais sênior.',
                    'recurring_themes' => 'Desenvolvimento profissional.',
                ],
                'with_link' => false,
            ],
            [
                'name' => 'Bruno Costa',
                'email' => 'bruno.costa@demo.local',
                'date' => '2026-06-22',
                'status' => ExitInterviewStatus::Completed,
                'answers' => [
                    'q1' => 'Aprendi muito, mas senti falta de feedbacks frequentes.',
                    'q4' => 'Pedido de demissão por realocação familiar.',
                    'q6' => 'Mais flexibilidade de horário poderia ter ajudado.',
                ],
                'notes' => [
                    'main_reasons' => 'Mudança de cidade.',
                ],
                'with_link' => false,
            ],
            [
                'name' => 'Camila Souza',
                'email' => 'camila.souza@demo.local',
                'date' => '2026-07-01',
                'status' => ExitInterviewStatus::Draft,
                'answers' => null,
                'notes' => null,
                'with_link' => true,
            ],
            [
                'name' => 'Diego Almeida',
                'email' => 'diego.almeida@demo.local',
                'date' => '2026-07-05',
                'status' => ExitInterviewStatus::Draft,
                'answers' => [
                    'q1' => 'Rascunho iniciado presencialmente.',
                ],
                'notes' => null,
                'with_link' => false,
            ],
            [
                'name' => 'Elisa Martins',
                'email' => 'elisa.martins@demo.local',
                'date' => '2026-07-08',
                'status' => ExitInterviewStatus::Completed,
                'answers' => [
                    'q1' => 'Ambiente colaborativo e com propósito claro.',
                    'q4' => 'Encerramento de contrato de projeto.',
                    'q7' => 'Tive boas oportunidades de aprendizado técnico.',
                    'q15' => 'Recomendaria a empresa para amigos da área.',
                ],
                'notes' => [
                    'main_reasons' => 'Fim do projeto.',
                    'company_recommendations' => 'Manter pipeline interno para realocação.',
                ],
                'with_link' => false,
            ],
            [
                'name' => 'Felipe Nogueira',
                'email' => null,
                'date' => null,
                'status' => ExitInterviewStatus::Draft,
                'answers' => null,
                'notes' => null,
                'with_link' => true,
            ],
        ];

        foreach ($samples as $sample) {
            $employee = CompanyEmployee::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'email' => $sample['email'] ?? 'sem-email-'.Str::slug($sample['name']).'@demo.local',
                ],
                [
                    'name' => $sample['name'],
                ]
            );

            $interview = ExitInterview::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'employee_name' => $sample['name'],
                ],
                [
                    'company_employee_id' => $employee->id,
                    'employee_email' => $sample['email'],
                    'interview_date' => $sample['date'],
                    'status' => $sample['status'],
                    'answers' => $sample['answers'],
                    'consultant_notes' => $sample['notes'],
                    'created_by' => $admin?->id,
                    'employee_submitted_at' => null,
                ]
            );

            if ($sample['with_link'] && $sample['status'] === ExitInterviewStatus::Draft) {
                $interview->ensurePublicToken();
            }
        }

        $count = ExitInterview::query()->where('company_id', $company->id)->count();
        $this->command?->info("Pesquisas de desligamento na {$company->name}: {$count}");

        $withLinks = ExitInterview::query()
            ->where('company_id', $company->id)
            ->whereNotNull('public_token')
            ->get(['employee_name', 'public_token']);

        foreach ($withLinks as $item) {
            $this->command?->line('  Link: '.$item->publicUrl().' ('.$item->employee_name.')');
        }
    }
}
