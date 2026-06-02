<?php

namespace Tests\Unit;

use App\Models\SurveyAnswer;
use App\Models\SurveyResponse;
use App\Models\SurveyResult;
use App\Models\SurveyTemplateQuestion;
use App\Services\SurveyResultCalculator;
use App\Support\Nr1Scoring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

class Nr1ScoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_risk_score_maps_likert_to_zero_to_hundred(): void
    {
        $question = new SurveyTemplateQuestion([
            'reverse_score' => false,
        ]);

        $this->assertSame(0.0, Nr1Scoring::normalizedRiskScore($question, 1));
        $this->assertSame(100.0, Nr1Scoring::normalizedRiskScore($question, 5));
        $this->assertSame(50.0, Nr1Scoring::normalizedRiskScore($question, 3));
    }

    public function test_reverse_score_inverts_protective_items(): void
    {
        $question = new SurveyTemplateQuestion([
            'reverse_score' => true,
        ]);

        $this->assertSame(0.0, Nr1Scoring::normalizedRiskScore($question, 5));
        $this->assertSame(100.0, Nr1Scoring::normalizedRiskScore($question, 1));
    }

    public function test_risk_level_uses_tercile_cutoffs(): void
    {
        $this->assertSame('green', Nr1Scoring::riskLevel(0));
        $this->assertSame('green', Nr1Scoring::riskLevel(33));
        $this->assertSame('yellow', Nr1Scoring::riskLevel(34));
        $this->assertSame('yellow', Nr1Scoring::riskLevel(66));
        $this->assertSame('red', Nr1Scoring::riskLevel(67));
        $this->assertSame('red', Nr1Scoring::riskLevel(100));
    }
}
