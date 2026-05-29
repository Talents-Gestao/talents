<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Department;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Position;
use App\Models\Subscription;
use App\Models\Survey;
use App\Models\SurveyTemplate;
use App\Models\SurveyTemplateQuestion;
use App\Models\SurveyTemplateSection;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TalentsSeeder extends Seeder
{
    public function run(): void
    {
        $nr1 = Module::query()->firstOrCreate(
            ['key' => 'nr1'],
            ['name' => 'NR-1 Riscos psicossociais', 'description' => 'Pesquisas e PGR']
        );

        $metodologia = Module::query()->firstOrCreate(
            ['key' => Module::KEY_METODOLOGIA],
            [
                'name' => 'Direcionamento Estratégico',
                'description' => 'Jornada de diagnóstico, pesquisa de satisfação e etapas do direcionamento estratégico.',
            ]
        );

        $calendario = Module::query()->firstOrCreate(
            ['key' => Module::KEY_CALENDARIO_ESTRATEGICO],
            [
                'name' => 'Calendário estratégico',
                'description' => 'Eventos e ritos orientados pela Talents para acompanhamento das empresas.',
            ]
        );

        $tarefas = Module::query()->firstOrCreate(
            ['key' => Module::KEY_TAREFAS],
            [
                'name' => 'Tarefas',
                'description' => 'Quadros Kanban e processos partilhados com empresas.',
            ]
        );

        $rhid = Module::query()->firstOrCreate(
            ['key' => Module::KEY_RHID],
            [
                'name' => 'RHID / Ponto',
                'description' => 'Integração de ponto eletrônico Control iD (espelho, compliance NR-1).',
            ]
        );

        $denuncias = Module::query()->firstOrCreate(
            ['key' => Module::KEY_DENUNCIAS],
            [
                'name' => 'Canal de denúncias',
                'description' => 'Canal de denúncias anônimas e gestão de protocolos (Lei nº 14.457/2022).',
            ]
        );

        $plan = Plan::query()->firstOrCreate(
            ['slug' => 'nr1-pro'],
            [
                'name' => 'NR1 Pro',
                'price_monthly_cents' => 127200,
                'max_employees' => 500,
                'max_surveys_per_year' => 12,
                'is_active' => true,
            ]
        );

        $plan->modules()->syncWithoutDetaching([$nr1->id, $metodologia->id, $calendario->id, $tarefas->id, $rhid->id, $denuncias->id]);

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@talents.local'],
            [
                'name' => 'Admin Talents',
                'password' => Hash::make('password'),
                'role' => UserRole::SuperAdmin,
                'company_id' => null,
                'is_owner' => true,
            ]
        );

        if (! $admin->is_owner) {
            $admin->update(['is_owner' => true]);
        }

        $company = Company::query()->firstOrCreate(
            ['cnpj' => '00.000.000/0001-99'],
            [
                'name' => 'Empresa Demo',
                'legal_name' => 'Empresa Demo LTDA',
                'segment' => 'tecnologia',
                'employee_count_estimate' => 120,
                'is_active' => true,
                'complaints_public_token' => (string) Str::uuid(),
            ]
        );

        if ($company->complaints_public_token === null) {
            $company->update(['complaints_public_token' => (string) Str::uuid()]);
        }

        Subscription::query()->firstOrCreate(
            ['company_id' => $company->id, 'plan_id' => $plan->id],
            [
                'starts_at' => now(),
                'ends_at' => now()->addYear(),
                'status' => 'active',
            ]
        );

        User::query()->firstOrCreate(
            ['email' => 'rh@empresa.local'],
            [
                'name' => 'RH Demo',
                'password' => Hash::make('password'),
                'role' => UserRole::CompanyAdmin,
                'company_id' => $company->id,
            ]
        );

        Department::query()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Operações'],
        );
        Department::query()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Administrativo'],
        );

        Position::query()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Analista'],
        );

        $copsoqTemplate = SurveyTemplate::query()->firstOrCreate(
            ['title' => 'NR-1 / COPSOQ (modelo Talents)'],
            [
                'description' => 'Template base para avaliação de riscos psicossociais (inspirado em COPSOQ).',
                'is_active' => true,
                'created_by' => $admin->id,
            ]
        );

        if ($copsoqTemplate->sections()->count() === 0) {
            $this->seedNr1Questions($copsoqTemplate);
        }

        $hseTemplate = SurveyTemplate::query()->firstOrCreate(
            ['title' => 'HSE-IT / Management Standards (NR-1)'],
            [
                'description' => 'Management Standards Indicator Tool (HSE/Reino Unido), 7 dimensões e 35 itens, adaptado ao português.',
                'is_active' => true,
                'created_by' => $admin->id,
            ]
        );

        if ($hseTemplate->sections()->count() === 0) {
            $this->seedHseItQuestions($hseTemplate);
        }

        $company->surveyTemplates()->syncWithoutDetaching([$copsoqTemplate->id, $hseTemplate->id]);

        $demo = Survey::query()->firstOrCreate(
            [
                'company_id' => $company->id,
                'title' => 'Campanha 2026 - Q1',
            ],
            [
                'survey_template_id' => $hseTemplate->id,
                'public_token' => (string) Str::uuid(),
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMonths(2),
                'status' => 'active',
                'min_responses_for_breakdown' => 1,
            ]
        );

        if ($demo->survey_template_id !== $hseTemplate->id) {
            $demo->update(['survey_template_id' => $hseTemplate->id]);
        }
    }

    private function seedNr1Questions(SurveyTemplate $template): void
    {
        $blocks = [
            [
                'title' => 'Exigências do trabalho',
                'questions' => [
                    ['body' => 'Com que frequência você precisa trabalhar muito rápido?', 'reverse_score' => false],
                    ['body' => 'Com que frequência sua carga de trabalho é excessiva?', 'reverse_score' => false],
                    ['body' => 'Com que frequência você tem prazos apertados?', 'reverse_score' => false],
                ],
            ],
            [
                'title' => 'Organização do trabalho',
                'questions' => [
                    ['body' => 'Você tem influência sobre a quantidade de trabalho que lhe é atribuída?', 'reverse_score' => true],
                    ['body' => 'Você tem possibilidade de aprender coisas novas no trabalho?', 'reverse_score' => true],
                    ['body' => 'Seu trabalho exige que você tome decisões importantes?', 'reverse_score' => true],
                ],
            ],
            [
                'title' => 'Relações interpessoais e liderança',
                'questions' => [
                    ['body' => 'Com que frequência você recebe ajuda e apoio de colegas?', 'reverse_score' => true],
                    ['body' => 'Com que frequência você recebe ajuda e apoio do seu superior imediato?', 'reverse_score' => true],
                    ['body' => 'A liderança trata os colaboradores com respeito?', 'reverse_score' => true],
                ],
            ],
            [
                'title' => 'Compensação e reconhecimento',
                'questions' => [
                    ['body' => 'Você se sente reconhecido pelo trabalho que realiza?', 'reverse_score' => true],
                    ['body' => 'As regras sobre promoções e recompensas são claras?', 'reverse_score' => true],
                    ['body' => 'Há transparência nas decisões que afetam seu trabalho?', 'reverse_score' => true],
                ],
            ],
            [
                'title' => 'Interface trabalho-vida',
                'questions' => [
                    ['body' => 'Com que frequência você leva o trabalho para casa?', 'reverse_score' => false],
                    ['body' => 'Seu trabalho afeta negativamente sua vida familiar/pessoal?', 'reverse_score' => false],
                    ['body' => 'Você tem tempo suficiente para sua vida fora do trabalho?', 'reverse_score' => true],
                ],
            ],
            [
                'title' => 'Saúde e bem-estar',
                'questions' => [
                    ['body' => 'Com que frequência você se sente emocionalmente esgotado?', 'reverse_score' => false],
                    ['body' => 'Com que frequência você se sente tenso ou nervoso?', 'reverse_score' => false],
                    ['body' => 'Com que frequência você dorme bem?', 'reverse_score' => true],
                ],
            ],
            [
                'title' => 'Assédio e violência',
                'questions' => [
                    ['body' => 'Com que frequência você foi alvo de comentários ofensivos ou humilhantes?', 'reverse_score' => false],
                    ['body' => 'Com que frequência presencia ou sofre assédio sexual no trabalho?', 'reverse_score' => false],
                    ['body' => 'Você se sente seguro no ambiente de trabalho?', 'reverse_score' => true],
                ],
            ],
        ];

        foreach ($blocks as $i => $block) {
            $section = SurveyTemplateSection::create([
                'survey_template_id' => $template->id,
                'title' => $block['title'],
                'description' => null,
                'sort_order' => $i,
            ]);

            foreach ($block['questions'] as $j => $q) {
                SurveyTemplateQuestion::create([
                    'survey_template_section_id' => $section->id,
                    'body' => $q['body'],
                    'reverse_score' => $q['reverse_score'],
                    'weight' => $q['weight'] ?? 1.0,
                    'response_scale' => $q['response_scale'] ?? 'frequency',
                    'sort_order' => $j,
                ]);
            }
        }
    }

    private function seedHseItQuestions(SurveyTemplate $template): void
    {
        foreach (HseItQuestionData::blocks() as $i => $block) {
            $section = SurveyTemplateSection::create([
                'survey_template_id' => $template->id,
                'title' => $block['title'],
                'description' => $block['description'] ?? null,
                'sort_order' => $i,
            ]);

            foreach ($block['questions'] as $j => $q) {
                SurveyTemplateQuestion::create([
                    'survey_template_section_id' => $section->id,
                    'body' => $q['body'],
                    'reverse_score' => $q['reverse_score'],
                    'weight' => $q['weight'] ?? 1.0,
                    'response_scale' => $q['response_scale'],
                    'sort_order' => $j,
                ]);
            }
        }
    }
}
