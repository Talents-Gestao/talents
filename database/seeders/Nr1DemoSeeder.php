<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Models\AdminUserPermission;
use App\Models\Company;
use App\Models\Department;
use App\Models\Module;
use App\Models\Nr1Training;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\PsychosocialMeasure;
use App\Models\Survey;
use App\Models\SurveyAudienceLink;
use App\Models\SurveyAnswer;
use App\Models\SurveyCampaignRegistration;
use App\Models\SurveyMethodVersion;
use App\Models\SurveyResponse;
use App\Models\SurveyTemplate;
use App\Models\User;
use App\Services\Nr1\Nr1TrainingService;
use App\Services\PsychosocialMeasureService;
use App\Services\SurveyResultCalculator;
use App\Support\Nr1InstrumentMatrix;
use App\Models\Nr1Opportunity;
use App\Models\PsychosocialReviewRound;
use App\Services\Nr1\Nr1OpportunityService;
use App\Services\Nr1\Nr1SolutionService;
use App\Services\Psychosocial\ReviewRoundService;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\Nr1V11Dataset;

/**
 * Demonstração do NR-1 V11 SOMENTE para ambiente local/testing: empresa e campanha do Apêndice A do Caderno
 * (40 respostas, 48 elegíveis, índice geral 2,60, 6 de 7 dimensões em atenção, Produção 19, Outros Setores 15, Manual 6).
 * Aborta em qualquer outro ambiente. Idempotente. Uso: php artisan db:seed --class=Nr1DemoSeeder
 */
class Nr1DemoSeeder extends Seeder
{
    public const COMPANY = 'Companhia do Livro (demo NR-1)';

    public const PASSWORD = 'Demo-12345-xyz';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Nr1DemoSeeder só roda em ambiente local ou testing (nunca em produção).');
        }

        Artisan::call('nr1:import-instrument-v11');
        $template = SurveyTemplate::query()->where('title', Talents39InstrumentData::TEMPLATE_TITLE)->firstOrFail();

        $company = Company::query()->firstOrCreate(['name' => self::COMPANY], [
            'cnpj' => '11.222.333/0001-81', 'is_active' => true, 'complaints_public_token' => (string) Str::uuid(),
        ]);

        if (! Subscription::query()->where('company_id', $company->id)->exists()) {
            $ids = [];
            foreach ([Module::KEY_NR1, Module::KEY_DENUNCIAS, Module::KEY_RHID] as $key) {
                $ids[] = Module::query()->firstOrCreate(['key' => $key], ['name' => $key, 'description' => 'Demo'])->id;
            }
            $plan = Plan::query()->create(['name' => 'Plano Demo NR-1', 'slug' => 'demo-nr1-'.Str::random(6), 'price_monthly_cents' => 0, 'is_active' => true]);
            $plan->modules()->sync($ids);
            Subscription::query()->create(['company_id' => $company->id, 'plan_id' => $plan->id, 'starts_at' => now(), 'ends_at' => now()->addYear(), 'status' => 'active']);
        }
        $company->surveyTemplates()->syncWithoutDetaching([$template->id]);

        $survey = Survey::query()->where('company_id', $company->id)->where('title', 'Campanha 2026')->first();
        if ($survey === null) {
            $survey = $this->createSurvey($company, $template);
        }

        $owner = User::query()->where('email', 'demo-owner@talents.local')->first()
            ?? User::factory()->talentsOwner()->create(['name' => 'Demo Talents', 'email' => 'demo-owner@talents.local', 'password' => Hash::make(self::PASSWORD)]);
        User::query()->where('email', 'demo-rh@demo-nr1.local')->first()
            ?? User::factory()->companyAdmin($company->id)->create(['name' => 'Demo RH', 'email' => 'demo-rh@demo-nr1.local', 'password' => Hash::make(self::PASSWORD)]);
        $this->commercial();
        $this->ensureCampaignContent($survey, $owner);
        $this->ensureLegacySurvey($company);
        $this->ensureReviewAndQueue($company, $template, $survey, $owner);

        $this->command?->info("Demo NR-1 pronta: empresa #{$company->id}, campanha #{$survey->id}. Logins (senha ".self::PASSWORD.'): '.$owner->email.', demo-rh@demo-nr1.local, demo-comercial@talents.local');
    }

    private function createSurvey(Company $company, SurveyTemplate $template, string $title = 'Campanha 2026', bool $published = true): Survey
    {
        $survey = Survey::query()->create([
            'company_id' => $company->id, 'survey_template_id' => $template->id, 'title' => $title,
            'public_token' => (string) Str::uuid(), 'starts_at' => now()->subDays(20), 'ends_at' => now()->addMonth(), 'status' => 'active',
            'method_version_id' => SurveyMethodVersion::idForCode(SurveyMethodVersion::CODE_TALENTS39_V1),
            'requires_publication' => true, 'published_at' => $published ? now() : null,
        ]);

        SurveyCampaignRegistration::query()->create([
            'survey_id' => $survey->id, 'company_id' => $company->id, 'legal_name_snapshot' => $company->name,
            'cnpj_snapshot' => $company->cnpj, 'eligible_count' => 48, 'eligible_source' => 'Informado pelo RH (demonstração)',
        ]);

        // Setores: Produção 19, Manual 6 e 15 respostas em cinco setores pequenos (< grupo mínimo), que viram «Outros Setores».
        $departments = [];
        foreach (['Produção' => 19, 'Manual' => 6, 'Financeiro' => 3, 'Comercial' => 3, 'Logística' => 3, 'Atendimento' => 3, 'Marketing' => 3] as $name => $count) {
            $id = Department::query()->firstOrCreate(['company_id' => $company->id, 'name' => $name])->id;
            $departments = array_merge($departments, array_fill(0, $count, $id));
        }

        $questions = [];
        foreach (Nr1InstrumentMatrix::forTemplate((int) $template->id) as $row) {
            $questions[$row['item_number']] = $row['question_id'];
        }
        $values = [];
        foreach (Nr1V11Dataset::COUNTS as $item => $counts) {
            $list = [];
            foreach ($counts as $index => $count) {
                $list = array_merge($list, array_fill(0, $count, $index + 1));
            }
            $values[$item] = $list;
        }

        for ($respondent = 0; $respondent < Nr1V11Dataset::RESPONDENTS; $respondent++) {
            $response = SurveyResponse::query()->create([
                'survey_id' => $survey->id, 'session_token' => (string) Str::uuid(),
                'department_id' => $departments[$respondent], 'completed_at' => now()->startOfMonth(),
            ]);
            SurveyAnswer::query()->insert(array_map(fn (int $item) => [
                'survey_response_id' => $response->id, 'survey_template_question_id' => $questions[$item],
                'value' => $values[$item][$respondent], 'created_at' => now(), 'updated_at' => now(),
            ], array_keys($questions)));
        }

        app(SurveyResultCalculator::class)->recalculate($survey->fresh());

        return $survey;
    }

    /** Plano sugerido (as 6 ações do protótipo), públicos da campanha e treinamento online contratado (idempotente). */
    private function ensureCampaignContent(Survey $survey, User $owner): void
    {
        foreach (SurveyAudienceLink::AUDIENCES as $audience) {
            SurveyAudienceLink::query()->firstOrCreate(['survey_id' => $survey->id, 'audience' => $audience], ['token' => (string) Str::uuid(), 'enabled' => true]);
        }

        if (PsychosocialMeasure::query()->where('survey_id', $survey->id)->count() < 6) {
            PsychosocialMeasure::query()->where('survey_id', $survey->id)->delete();
            $actions = [
                ['Demandas e Sobrecarga', 'Revisar volume, ritmo, metas, processos e distribuição das demandas.', 'Gestão de Operações', 30],
                ['Segurança Psicológica', 'Fortalecer os canais de escuta, confiança e comunicação segura.', 'RH', 30],
                ['Esgotamento e Saúde Emocional', 'Reavaliar picos de trabalho, pausas, jornadas e práticas preventivas.', 'RH', 45],
                ['Liderança e Gestão', 'Desenvolver líderes em feedback, comunicação e apoio às equipes.', 'Diretoria', 45],
                ['Relacionamentos e Clima', 'Aprimorar acordos de convivência e gestão construtiva de conflitos.', 'RH', 60],
                ['Percepção Organizacional', 'Aprimorar a comunicação das decisões e a escuta sobre mudanças.', 'Diretoria', 60],
            ];
            foreach ($actions as $index => [$factor, $description, $owner_, $days]) {
                $measure = app(PsychosocialMeasureService::class)->createEngineSuggestion($survey, [
                    'factor_label' => $factor, 'measure_description' => $description,
                    'suggested_due_date' => now()->addDays($days)->toDateString(), 'sort_order' => $index + 1,
                ]);
                $measure->forceFill(['responsible_text' => $owner_])->save(); // demonstração: a campanha já está publicada
            }
        }

        foreach (SurveyAudienceLink::AUDIENCES as $audience) {
            if (! Nr1Training::query()->where('survey_id', $survey->id)->where('modality', Nr1Training::MODALITY_ONLINE)->where('audience', $audience)->exists()) {
                app(Nr1TrainingService::class)->contract($survey, $owner, Nr1Training::MODALITY_ONLINE, $audience, [
                    'scope' => 'Treinamento online contratado (demonstração)', 'link_url' => 'https://exemplo.talents.local/treinamento', 'released' => true,
                ]);
            }
        }
    }

    /**
     * Campanha ANTIGA fictícia (modelo anterior, sem fluxo de revisão) para validar o legado dentro do shell:
     * template COPSOQ da Talents, 36 respostas em 6 setores (mínimo por setor = 1, como no legado), resultados calculados.
     */
    private function ensureLegacySurvey(Company $company): void
    {
        if (Survey::query()->where('company_id', $company->id)->where('title', 'Campanha 2025 (modelo anterior)')->exists()) {
            return;
        }

        $template = SurveyTemplate::query()->where('title', 'like', 'NR-1 / COPSOQ%')->first();
        if ($template === null) {
            return; // o template do legado vem do seeder principal; sem ele não há o que demonstrar
        }
        $company->surveyTemplates()->syncWithoutDetaching([$template->id]);

        $survey = Survey::query()->create([
            'company_id' => $company->id, 'survey_template_id' => $template->id, 'title' => 'Campanha 2025 (modelo anterior)',
            'public_token' => (string) Str::uuid(), 'starts_at' => now()->subMonths(8), 'ends_at' => now()->subMonths(7), 'status' => 'closed',
            'min_responses_for_breakdown' => 1, 'requires_publication' => false,
        ]);

        $departments = [];
        foreach (['Produção' => 14, 'Manual' => 7, 'Financeiro' => 5, 'Comercial' => 4, 'Logística' => 3, 'Atendimento' => 3] as $name => $count) {
            $id = Department::query()->firstOrCreate(['company_id' => $company->id, 'name' => $name])->id;
            $departments = array_merge($departments, array_fill(0, $count, $id));
        }

        $questions = $template->sections()->with('questions')->get()->flatMap->questions;
        mt_srand(2025); // determinístico: o demo sempre gera os mesmos números
        foreach ($departments as $departmentId) {
            $response = SurveyResponse::query()->create([
                'survey_id' => $survey->id, 'session_token' => (string) Str::uuid(), 'department_id' => $departmentId, 'completed_at' => now()->subMonths(7),
            ]);
            $bias = $departmentId % 3; // cada setor pende para um lado, para a tabela de calor ter variação
            SurveyAnswer::query()->insert($questions->map(fn ($q) => [
                'survey_response_id' => $response->id, 'survey_template_question_id' => $q->id,
                'value' => max(1, min(5, mt_rand(1, 5) + $bias - 1)), 'created_at' => now(), 'updated_at' => now(),
            ])->all());
        }

        app(SurveyResultCalculator::class)->recalculate($survey->fresh());
    }

    /**
     * Rodada de revisão em estados variados e fila comercial (demonstração do shell NR-1, Etapa D):
     * campanha 2026 com a rodada 1 PUBLICADA e a rodada 2 em edição (a empresa continua vendo a publicada);
     * «Unidade Sul» (não publicada) com a rodada em revisão PASSEG; oportunidades em estágios diferentes.
     */
    private function ensureReviewAndQueue(Company $company, SurveyTemplate $template, Survey $survey, User $owner): void
    {
        $rounds = app(ReviewRoundService::class);

        // Cadastro técnico mínimo para o checklist de publicação (dados fictícios de demonstração).
        SurveyCampaignRegistration::query()->where('survey_id', $survey->id)->update([
            'unit_name' => 'Matriz', 'technical_responsible_name' => 'Responsável Técnica (demo)',
            'technical_responsible_role' => 'Psicóloga', 'technical_responsible_registration' => 'CRP 06/000000 (demo)',
        ]);

        if (PsychosocialReviewRound::query()->where('survey_id', $survey->id)->where('status', PsychosocialReviewRound::STATUS_PUBLISHED)->doesntExist()) {
            $previous = $survey->published_at;
            $survey->forceFill(['published_at' => null])->save();
            try {
                $round = $rounds->currentRound($survey->fresh()) ?? $rounds->start($survey->fresh(), $owner);
                $rounds->regenerate($round, $owner);
                $rounds->publish($round->fresh(), $owner);
            } catch (\Throwable $exception) {
                $survey->forceFill(['published_at' => $previous])->save();
                $this->command?->warn('Rodada demo da campanha 2026 não publicada: '.$exception->getMessage());
            }
        }
        if (PsychosocialReviewRound::query()->where('survey_id', $survey->id)->where('status', PsychosocialReviewRound::STATUS_PUBLISHED)->exists()
            && PsychosocialReviewRound::query()->where('survey_id', $survey->id)->where('status', '!=', PsychosocialReviewRound::STATUS_PUBLISHED)->doesntExist()) {
            $second = $rounds->start($survey->fresh(), $owner);
            $rounds->regenerate($second, $owner);
        }

        $south = Survey::query()->where('company_id', $company->id)->where('title', 'Campanha 2026 · Unidade Sul')->first()
            ?? $this->createSurvey($company, $template, 'Campanha 2026 · Unidade Sul', false);
        if (PsychosocialReviewRound::query()->where('survey_id', $south->id)->doesntExist()) {
            $round = $rounds->start($south, $owner);
            $rounds->regenerate($round, $owner);
            // Estado de demonstração: enviada à PASSEG (o envio real exige revisor cadastrado).
            DB::table('psychosocial_review_rounds')->where('id', $round->id)->update(['status' => PsychosocialReviewRound::STATUS_IN_REVIEW, 'sent_at' => now(), 'sent_by' => $owner->id]);
        }

        if (Nr1Opportunity::query()->where('survey_id', $survey->id)->doesntExist()) {
            $rh = User::query()->where('email', 'demo-rh@demo-nr1.local')->first();
            $commercial = User::query()->where('email', 'demo-comercial@talents.local')->first();
            $opportunities = app(Nr1OpportunityService::class);
            if ($rh !== null) {
                $first = $opportunities->requestTraining($survey, $rh, 'presencial', 'time');
                $second = $opportunities->requestTraining($survey, $rh, 'presencial', 'lideres');
                if ($commercial !== null) {
                    $opportunities->assume($first, $commercial);
                    $opportunities->changeStatus($first->fresh(), $commercial, Nr1Opportunity::STATUS_IN_CONTACT, 'Primeiro contato com o RH feito.');
                    $opportunities->setNextAction($first->fresh(), $commercial, 'Enviar proposta de treinamento presencial para o time', now()->addDays(3)->toDateString());
                    $opportunities->assume($second, $commercial);
                }
            }
            app(Nr1SolutionService::class)->suggest($survey->fresh());
        }
    }

    private function commercial(): void
    {
        $user = User::query()->where('email', 'demo-comercial@talents.local')->first()
            ?? User::factory()->superAdmin()->create(['is_owner' => false, 'name' => 'Demo Comercial', 'email' => 'demo-comercial@talents.local', 'password' => Hash::make(self::PASSWORD)]);
        $workspaceId = $user->workspaces()->firstOrFail()->id;
        foreach ([PermissionAction::View, PermissionAction::Edit] as $action) {
            AdminUserPermission::query()->firstOrCreate(['user_workspace_id' => $workspaceId, 'module' => AdminPermissionModule::Nr1Oportunidades, 'action' => $action]);
        }
    }
}
