<?php

namespace Tests\Unit;

use App\Models\SurveyResult;
use App\Support\Nr1RiskScenarioResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

class Nr1RiskScenarioResolverTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    public function test_resolves_overall_risk_level_from_survey_results(): void
    {
        $fx = $this->createSurveyFixture();

        SurveyResult::query()->create([
            'survey_id' => $fx->survey->id,
            'survey_template_section_id' => null,
            'department_id' => null,
            'average_score' => 3.8,
            'risk_level' => 'red',
            'respondent_count' => 5,
            'meta' => [],
        ]);

        $this->assertSame('red', Nr1RiskScenarioResolver::forSurvey($fx->survey));
        $this->assertSame('Cenário geral: Alto risco', Nr1RiskScenarioResolver::scenarioConfig('red')['short_label']);
    }

    public function test_returns_null_when_no_overall_result(): void
    {
        $fx = $this->createSurveyFixture();

        $this->assertNull(Nr1RiskScenarioResolver::forSurvey($fx->survey));
    }

    public function test_normalize_accepts_only_valid_risk_levels(): void
    {
        $this->assertSame('green', Nr1RiskScenarioResolver::normalize('green'));
        $this->assertSame('yellow', Nr1RiskScenarioResolver::normalize('yellow'));
        $this->assertSame('red', Nr1RiskScenarioResolver::normalize('red'));
        $this->assertNull(Nr1RiskScenarioResolver::normalize('invalid'));
        $this->assertNull(Nr1RiskScenarioResolver::normalize(null));
    }

    public function test_scenario_config_returns_copy_for_each_risk_level(): void
    {
        $this->assertSame('Cenário geral: Baixo risco', Nr1RiskScenarioResolver::scenarioConfig('green')['short_label']);
        $this->assertSame('Cenário geral: Atenção', Nr1RiskScenarioResolver::scenarioConfig('yellow')['short_label']);
        $this->assertSame('Cenário geral: Alto risco', Nr1RiskScenarioResolver::scenarioConfig('red')['short_label']);
    }

    public function test_resolve_returns_scenario_and_config_together(): void
    {
        $fx = $this->createSurveyFixture();

        SurveyResult::query()->create([
            'survey_id' => $fx->survey->id,
            'survey_template_section_id' => null,
            'department_id' => null,
            'average_score' => 3.0,
            'risk_level' => 'yellow',
            'respondent_count' => 8,
            'meta' => [],
        ]);

        $resolved = Nr1RiskScenarioResolver::resolve($fx->survey);

        $this->assertSame('yellow', $resolved['scenario']);
        $this->assertSame('preventiva_com_acompanhamento', $resolved['config']['action_plan']['item_kind']);
    }
}
