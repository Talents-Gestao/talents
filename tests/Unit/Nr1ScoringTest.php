<?php

namespace Tests\Unit;

use App\Models\SurveyTemplateQuestion;
use App\Support\Nr1Scoring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Nr1ScoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_effective_likert_preserves_risk_item_values(): void
    {
        $question = new SurveyTemplateQuestion([
            'reverse_score' => false,
        ]);

        $this->assertSame(1.0, Nr1Scoring::effectiveLikertValue($question, 1));
        $this->assertSame(5.0, Nr1Scoring::effectiveLikertValue($question, 5));
        $this->assertSame(3.0, Nr1Scoring::effectiveLikertValue($question, 3));
    }

    public function test_reverse_score_inverts_protective_items(): void
    {
        $question = new SurveyTemplateQuestion([
            'reverse_score' => true,
        ]);

        $this->assertSame(1.0, Nr1Scoring::effectiveLikertValue($question, 5));
        $this->assertSame(5.0, Nr1Scoring::effectiveLikertValue($question, 1));
    }

    public function test_risk_level_uses_likert_tercile_cutoffs(): void
    {
        $this->assertSame('green', Nr1Scoring::riskLevel(1));
        $this->assertSame('green', Nr1Scoring::riskLevel(2.33));
        $this->assertSame('yellow', Nr1Scoring::riskLevel(2.34));
        $this->assertSame('yellow', Nr1Scoring::riskLevel(3.66));
        $this->assertSame('red', Nr1Scoring::riskLevel(3.67));
        $this->assertSame('red', Nr1Scoring::riskLevel(5));
    }
}
