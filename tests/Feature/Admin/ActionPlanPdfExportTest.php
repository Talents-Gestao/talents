<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\ActionPlan;
use App\Models\ActionPlanItem;
use App\Models\User;
use App\Services\ReportGenerator;
use App\Support\Nr1RiskScenarioResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\SeedsNr1SurveyResults;
use Tests\TestCase;

class ActionPlanPdfExportTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;
    use SeedsNr1SurveyResults;

    public function test_action_plan_blade_omits_actions_section(): void
    {
        $fx = $this->createSurveyFixture();
        $this->seedNr1OverallAndSectionResult($fx, 'yellow', 3.0);
        $this->createPublishedPlanWithItem($fx, 'Ação de teste PDF');

        $survey = $fx->survey->fresh()->load(['company', 'results', 'insights']);
        $scenario = Nr1RiskScenarioResolver::forSurvey($survey) ?? 'yellow';
        $plan = ActionPlan::query()->where('survey_id', $survey->id)->firstOrFail();

        $html = view('reports.action_plan', [
            'survey' => $survey,
            'scenario' => $scenario,
            'scenarioConfig' => Nr1RiskScenarioResolver::scenarioConfig($scenario),
            'riskLevelLabel' => fn (?string $l) => config('nr1.risk_labels.'.$l, $l),
            'riskColor' => fn (?string $l) => '#000',
            'logoBase64' => null,
            'plan' => $plan,
            'technicalOpinion' => null,
            'overall' => null,
            'bySection' => [],
            'deptOveralls' => [],
            'deptSectionsByDepartment' => [],
            'departmentParticipation' => [],
            'questionDistributions' => [],
            'insights' => collect(),
            'radarSvg' => null,
            'heatmapCell' => fn () => null,
            'likertLabel' => fn () => '',
        ])->render();

        $this->assertStringNotContainsString('<h2>Ações</h2>', $html);
        $this->assertStringNotContainsString('Ação de teste PDF', $html);
        $this->assertStringNotContainsString('validado pela equipe de SST', $html);
    }

    public function test_admin_can_download_results_pdf(): void
    {
        $fx = $this->createSurveyFixture();
        $this->seedNr1OverallAndSectionResult($fx, 'yellow', 3.0);
        $this->createPublishedPlanWithItem($fx, 'Ação endpoint PDF');

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $response = $this->actingAs($admin)
            ->get(route('admin.companies.surveys.action-plan.pdf', [
                'company' => $fx->company->id,
                'survey' => $fx->survey->id,
            ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    public function test_report_generator_builds_results_pdf(): void
    {
        $fx = $this->createSurveyFixture();
        $this->seedNr1OverallAndSectionResult($fx, 'green', 2.0);
        $this->createPublishedPlanWithItem($fx, 'Ação default');

        $pdf = app(ReportGenerator::class)->actionPlanPdf($fx->survey->fresh());
        $output = $pdf->output();

        $this->assertNotEmpty($output);
        $this->assertStringStartsWith('%PDF', $output);
    }

    public function test_document_blade_contains_only_technical_opinion(): void
    {
        $fx = $this->createSurveyFixture();
        $this->seedNr1OverallAndSectionResult($fx, 'yellow', 3.0);

        $survey = $fx->survey->fresh()->load('company');
        $scenario = Nr1RiskScenarioResolver::forSurvey($survey) ?? 'yellow';

        $html = view('reports.action_plan_document', [
            'survey' => $survey,
            'scenario' => $scenario,
            'scenarioConfig' => Nr1RiskScenarioResolver::scenarioConfig($scenario),
            'logoBase64' => null,
            'technicalOpinion' => '<p>Parecer de rascunho</p>',
            'isDraft' => true,
        ])->render();

        $this->assertStringContainsString('Parecer de rascunho', $html);
        $this->assertStringContainsString('Parecer técnico NR-1', $html);
        $this->assertStringNotContainsString('<h2>Ações</h2>', $html);
        $this->assertStringNotContainsString('Ação individual PDF', $html);
        $this->assertStringContainsString('Rascunho — este documento ainda não foi publicado', $html);
        $this->assertStringNotContainsString('Indicador geral de risco', $html);
        $this->assertStringNotContainsString('<h2>Dimensões</h2>', $html);
    }

    public function test_admin_can_preview_document_pdf_from_form_without_publishing(): void
    {
        $fx = $this->createSurveyFixture();
        $this->seedNr1OverallAndSectionResult($fx, 'yellow', 3.0);

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $response = $this->actingAs($admin)
            ->post(route('admin.companies.surveys.action-plan.document-pdf', [
                'company' => $fx->company->id,
                'survey' => $fx->survey->id,
            ]), [
                'technical_opinion' => '<p>Parecer ainda não publicado</p>',
            ]);

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertFalse(
            ActionPlan::query()->where('survey_id', $fx->survey->id)->exists()
        );
    }

    public function test_admin_can_download_unpublished_document_pdf_from_saved_draft(): void
    {
        $fx = $this->createSurveyFixture();
        $this->seedNr1OverallAndSectionResult($fx, 'green', 2.0);

        $plan = ActionPlan::query()->create([
            'company_id' => $fx->company->id,
            'survey_id' => $fx->survey->id,
            'status' => 'open',
            'admin_published_at' => null,
            'technical_opinion' => '<p>Parecer salvo</p>',
        ]);

        ActionPlanItem::query()->create([
            'action_plan_id' => $plan->id,
            'title' => 'Ação não publicada',
            'description' => 'Rascunho',
            'status' => 'pending',
            'sort_order' => 0,
        ]);

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $response = $this->actingAs($admin)
            ->get(route('admin.companies.surveys.action-plan.document-pdf', [
                'company' => $fx->company->id,
                'survey' => $fx->survey->id,
            ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertNull($plan->fresh()->admin_published_at);
    }

    public function test_client_cannot_download_unpublished_action_plan_document(): void
    {
        $fx = $this->createSurveyFixture();
        $this->seedNr1OverallAndSectionResult($fx, 'green', 2.0);

        $plan = ActionPlan::query()->create([
            'company_id' => $fx->company->id,
            'survey_id' => $fx->survey->id,
            'status' => 'open',
            'admin_published_at' => null,
            'technical_opinion' => '<p>Segredo</p>',
        ]);

        ActionPlanItem::query()->create([
            'action_plan_id' => $plan->id,
            'title' => 'Ação secreta',
            'description' => 'Não publicar',
            'status' => 'pending',
            'sort_order' => 0,
        ]);

        $user = User::factory()->companyAdmin($fx->company->id)->create();

        $this->actingAs($user)
            ->get(route('client.surveys.reports.action-plan', $fx->survey))
            ->assertNotFound();
    }

    public function test_client_can_download_published_action_plan_document(): void
    {
        $fx = $this->createSurveyFixture();
        $this->seedNr1OverallAndSectionResult($fx, 'green', 2.0);
        $this->createPublishedPlanWithItem($fx, 'Ação publicada');

        $user = User::factory()->companyAdmin($fx->company->id)->create();

        $response = $this->actingAs($user)
            ->get(route('client.surveys.reports.action-plan', $fx->survey));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    /**
     * @param  object{company: \App\Models\Company, survey: \App\Models\Survey}  $fx
     */
    private function createPublishedPlanWithItem(object $fx, string $title): ActionPlan
    {
        $plan = ActionPlan::query()->create([
            'company_id' => $fx->company->id,
            'survey_id' => $fx->survey->id,
            'status' => 'published',
            'admin_published_at' => now(),
            'technical_opinion' => null,
        ]);

        ActionPlanItem::query()->create([
            'action_plan_id' => $plan->id,
            'title' => $title,
            'description' => 'Descrição de teste',
            'status' => 'pending',
            'sort_order' => 0,
        ]);

        return $plan;
    }
}
