<?php

namespace Tests\Unit;

use App\Services\ActionPlanGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\SeedsNr1SurveyResults;
use Tests\TestCase;

class ActionPlanGeneratorTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;
    use SeedsNr1SurveyResults;

    public function test_red_scenario_generates_immediate_intervention_items(): void
    {
        $fx = $this->createSurveyFixture();
        $this->seedNr1OverallAndSectionResult($fx, 'red');

        $plan = app(ActionPlanGenerator::class)->generate($fx->survey->fresh());

        $this->assertGreaterThan(0, $plan->items->count());
        $this->assertStringContainsString('Intervenção imediata', (string) $plan->items->first()->title);
        $this->assertStringContainsString('Prazo: curto', (string) $plan->items->first()->description);
    }

    public function test_yellow_scenario_generates_preventive_items_with_mandatory_follow_up(): void
    {
        $fx = $this->createSurveyFixture();
        $this->seedNr1OverallAndSectionResult($fx, 'yellow', 3.0);

        $plan = app(ActionPlanGenerator::class)->generate($fx->survey->fresh());

        $this->assertGreaterThan(0, $plan->items->count());
        $this->assertStringContainsString('acompanhamento obrigatório', (string) $plan->items->first()->title);
        $this->assertStringContainsString('acompanhamento obrigatório', (string) $plan->items->first()->description);
    }

    public function test_green_scenario_skips_green_sections_and_uses_preventive_prefix(): void
    {
        $fx = $this->createSurveyFixture();
        $this->seedNr1OverallAndSectionResult($fx, 'green', 2.0);

        $plan = app(ActionPlanGenerator::class)->generate($fx->survey->fresh());

        $this->assertSame(1, $plan->items->count());
        $this->assertSame('Manter práticas e monitorar indicadores', $plan->items->first()->title);
    }

    public function test_green_scenario_with_non_green_section_creates_preventive_action(): void
    {
        $fx = $this->createSurveyFixture();

        $this->seedNr1OverallAndSectionResult($fx, 'green', 2.0);

        \App\Models\SurveyResult::query()
            ->where('survey_id', $fx->survey->id)
            ->where('survey_template_section_id', $fx->section->id)
            ->update([
                'average_score' => 3.5,
                'risk_level' => 'yellow',
            ]);

        $plan = app(ActionPlanGenerator::class)->generate($fx->survey->fresh());

        $this->assertGreaterThan(0, $plan->items->count());
        $this->assertStringContainsString('Ação preventiva', (string) $plan->items->first()->title);
    }

    public function test_regenerating_plan_replaces_previous_items(): void
    {
        $fx = $this->createSurveyFixture();
        $this->seedNr1OverallAndSectionResult($fx, 'red');

        $generator = app(ActionPlanGenerator::class);
        $first = $generator->generate($fx->survey->fresh());
        $firstItemIds = $first->items->pluck('id')->all();

        $second = $generator->generate($fx->survey->fresh());
        $secondItemIds = $second->items->pluck('id')->all();

        $this->assertNotEquals($firstItemIds, $secondItemIds);
        $this->assertSame($first->id, $second->id);
    }
}
