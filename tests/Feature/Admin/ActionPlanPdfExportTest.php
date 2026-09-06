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

    public function test_action_plan_blade_omits_actions_section_when_flag_is_false(): void
    {
        $fx = $this->createSurveyFixture();
        $this->seedNr1OverallAndSectionResult($fx, 'yellow', 3.0);
        $this->createPublishedPlanWithItem($fx, 'Ação de teste PDF');

        $survey = $fx->survey->fresh()->load(['company', 'results', 'insights']);
        $scenario = Nr1RiskScenarioResolver::forSurvey($survey) ?? 'yellow';
        $plan = ActionPlan::query()->where('survey_id', $survey->id)->with('items')->firstOrFail();

        $htmlWithout = view('reports.action_plan', [
            'survey' => $survey,
            'scenario' => $scenario,
            'scenarioConfig' => Nr1RiskScenarioResolver::scenarioConfig($scenario),
            'riskLevelLabel' => fn (?string $l) => config('nr1.risk_labels.'.$l, $l),
            'riskColor' => fn (?string $l) => '#000',
            'logoBase64' => null,
            'plan' => $plan,
            'items' => $plan->items,
            'includeActions' => false,
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

        $this->assertStringNotContainsString('<h2>Ações</h2>', $htmlWithout);
        $this->assertStringNotContainsString('Ação de teste PDF', $htmlWithout);
        $this->assertStringNotContainsString('validado pela equipe de SST', $htmlWithout);

        $htmlWith = view('reports.action_plan', [
            'survey' => $survey,
            'scenario' => $scenario,
            'scenarioConfig' => Nr1RiskScenarioResolver::scenarioConfig($scenario),
            'riskLevelLabel' => fn (?string $l) => config('nr1.risk_labels.'.$l, $l),
            'riskColor' => fn (?string $l) => '#000',
            'logoBase64' => null,
            'plan' => $plan,
            'items' => $plan->items,
            'includeActions' => true,
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

        $this->assertStringContainsString('<h2>Ações</h2>', $htmlWith);
        $this->assertStringContainsString('Ação de teste PDF', $htmlWith);
        $this->assertStringContainsString('validado pela equipe de SST', $htmlWith);
    }

    public function test_admin_pdf_endpoint_respects_include_actions_query(): void
    {
        $fx = $this->createSurveyFixture();
        $this->seedNr1OverallAndSectionResult($fx, 'yellow', 3.0);
        $this->createPublishedPlanWithItem($fx, 'Ação endpoint PDF');

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $withActions = $this->actingAs($admin)
            ->get(route('admin.companies.surveys.action-plan.pdf', [
                'company' => $fx->company->id,
                'survey' => $fx->survey->id,
                'include_actions' => 1,
            ]));

        $withActions->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $withActions->headers->get('content-type'));

        $withoutActions = $this->actingAs($admin)
            ->get(route('admin.companies.surveys.action-plan.pdf', [
                'company' => $fx->company->id,
                'survey' => $fx->survey->id,
                'include_actions' => 0,
            ]));

        $withoutActions->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $withoutActions->headers->get('content-type'));

        // PDF binário difere quando a seção Ações é omitida.
        $this->assertNotSame($withActions->getContent(), $withoutActions->getContent());
    }

    public function test_report_generator_defaults_to_including_actions(): void
    {
        $fx = $this->createSurveyFixture();
        $this->seedNr1OverallAndSectionResult($fx, 'green', 2.0);
        $this->createPublishedPlanWithItem($fx, 'Ação default');

        $pdf = app(ReportGenerator::class)->actionPlanPdf($fx->survey->fresh());
        $output = $pdf->output();

        $this->assertNotEmpty($output);
        $this->assertStringStartsWith('%PDF', $output);
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
