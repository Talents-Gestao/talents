<?php

namespace Tests\Unit;

use App\Models\SurveyAnswer;
use App\Models\SurveyResponse;
use App\Models\SurveyResult;
use App\Models\SurveyTemplateQuestion;
use App\Services\SurveyProfileResponseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

class SurveyProfileResponseSeederTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    public function test_seeds_favorable_and_unfavorable_profiles_with_replace(): void
    {
        $fx = $this->createSurveyFixture();

        SurveyTemplateQuestion::query()->whereKey($fx->question->id)->update([
            'reverse_score' => false,
        ]);

        $seeder = app(SurveyProfileResponseSeeder::class);
        $seeder->seed($fx->survey, favorableCount: 2, unfavorableCount: 1, replaceExisting: true);

        $this->assertSame(3, SurveyResponse::query()->where('survey_id', $fx->survey->id)->count());
        $this->assertSame(3, SurveyAnswer::query()->count());

        $overall = SurveyResult::query()
            ->where('survey_id', $fx->survey->id)
            ->whereNull('survey_template_section_id')
            ->whereNull('department_id')
            ->first();

        $this->assertNotNull($overall);
        $this->assertSame(3, $overall->respondent_count);
        $this->assertGreaterThan(1.0, (float) $overall->average_score);
        $this->assertLessThan(5.0, (float) $overall->average_score);
    }

    public function test_favorable_profile_produces_low_risk_on_risk_item(): void
    {
        $fx = $this->createSurveyFixture();

        SurveyTemplateQuestion::query()->whereKey($fx->question->id)->update([
            'reverse_score' => false,
        ]);

        app(SurveyProfileResponseSeeder::class)->seed($fx->survey, 1, 0, true);

        $answer = SurveyAnswer::query()->first();
        $this->assertNotNull($answer);
        $this->assertLessThanOrEqual(2, $answer->value);

        $overall = SurveyResult::query()
            ->where('survey_id', $fx->survey->id)
            ->whereNull('survey_template_section_id')
            ->whereNull('department_id')
            ->first();

        $this->assertSame('green', $overall->risk_level);
    }

    public function test_unfavorable_profile_produces_high_risk_on_risk_item(): void
    {
        $fx = $this->createSurveyFixture();

        SurveyTemplateQuestion::query()->whereKey($fx->question->id)->update([
            'reverse_score' => false,
        ]);

        app(SurveyProfileResponseSeeder::class)->seed($fx->survey, 0, 1, true);

        $answer = SurveyAnswer::query()->first();
        $this->assertNotNull($answer);
        $this->assertGreaterThanOrEqual(4, $answer->value);

        $overall = SurveyResult::query()
            ->where('survey_id', $fx->survey->id)
            ->whereNull('survey_template_section_id')
            ->whereNull('department_id')
            ->first();

        $this->assertSame('red', $overall->risk_level);
    }
}
