<?php

namespace Tests\Unit;

use App\Models\SurveyAnswer;
use App\Models\SurveyResponse;
use App\Models\SurveyResult;
use App\Models\SurveyTemplateQuestion;
use App\Services\SurveyResultCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

class SurveyResultCalculatorRiskTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    public function test_high_likert_on_risk_item_produces_high_risk_score(): void
    {
        $fx = $this->createSurveyFixture();

        SurveyTemplateQuestion::query()->whereKey($fx->question->id)->update([
            'reverse_score' => false,
        ]);

        $response = SurveyResponse::query()->create([
            'survey_id' => $fx->survey->id,
            'session_token' => 'token-a',
            'completed_at' => now(),
        ]);

        SurveyAnswer::query()->create([
            'survey_response_id' => $response->id,
            'survey_template_question_id' => $fx->question->id,
            'value' => 5,
        ]);

        app(SurveyResultCalculator::class)->recalculate($fx->survey->fresh());

        $overall = SurveyResult::query()
            ->where('survey_id', $fx->survey->id)
            ->whereNull('survey_template_section_id')
            ->whereNull('department_id')
            ->first();

        $this->assertNotNull($overall);
        $this->assertSame(5.0, (float) $overall->average_score);
        $this->assertSame('red', $overall->risk_level);
    }

    public function test_low_likert_on_risk_item_produces_favorable_score(): void
    {
        $fx = $this->createSurveyFixture();

        SurveyTemplateQuestion::query()->whereKey($fx->question->id)->update([
            'reverse_score' => false,
        ]);

        $response = SurveyResponse::query()->create([
            'survey_id' => $fx->survey->id,
            'session_token' => 'token-a',
            'completed_at' => now(),
        ]);

        SurveyAnswer::query()->create([
            'survey_response_id' => $response->id,
            'survey_template_question_id' => $fx->question->id,
            'value' => 1,
        ]);

        app(SurveyResultCalculator::class)->recalculate($fx->survey->fresh());

        $overall = SurveyResult::query()
            ->where('survey_id', $fx->survey->id)
            ->whereNull('survey_template_section_id')
            ->whereNull('department_id')
            ->first();

        $this->assertNotNull($overall);
        $this->assertSame(1.0, (float) $overall->average_score);
        $this->assertSame('green', $overall->risk_level);
    }
}
