<?php

namespace Tests\Feature\Public;

use App\Models\Department;
use App\Models\SurveyResponse;
use App\Services\SurveyResultCalculator;
use App\Services\SurveyResultsPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

class PublicSurveyDepartmentResultsTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    public function test_submit_stores_department_and_results_include_sector_breakdown(): void
    {
        $fx = $this->createSurveyFixture([
            'min_responses_for_breakdown' => 1,
        ]);

        $dept = Department::query()->create([
            'company_id' => $fx->company->id,
            'name' => 'Operações',
        ]);

        $this->post(route('survey.public.submit', $fx->survey->public_token), [
            'answers' => [$fx->question->id => 4],
            'department_id' => (string) $dept->id,
        ])->assertRedirect();

        $response = SurveyResponse::query()->where('survey_id', $fx->survey->id)->first();

        $this->assertNotNull($response);
        $this->assertSame($dept->id, (int) $response->department_id);

        $presented = SurveyResultsPresenter::forSurvey($fx->survey->fresh());

        $this->assertNotEmpty($presented['deptOveralls']);
        $this->assertSame('Operações', $presented['deptOveralls'][0]['department_name']);
    }

    public function test_department_participation_marks_sector_ready_with_one_response(): void
    {
        $fx = $this->createSurveyFixture([
            'min_responses_for_breakdown' => 60,
        ]);

        $dept = Department::query()->create([
            'company_id' => $fx->company->id,
            'name' => 'Operações',
        ]);

        SurveyResponse::query()->create([
            'survey_id' => $fx->survey->id,
            'session_token' => 'abc',
            'department_id' => $dept->id,
            'completed_at' => now(),
        ])->answers()->create([
            'survey_template_question_id' => $fx->question->id,
            'value' => 4,
        ]);

        app(SurveyResultCalculator::class)->recalculate($fx->survey->fresh());

        $presented = SurveyResultsPresenter::forSurvey($fx->survey->fresh());

        $this->assertCount(1, $presented['departmentParticipation']);
        $this->assertTrue($presented['departmentParticipation'][0]['meets_minimum']);
        $this->assertNotEmpty($presented['deptOveralls']);
    }

    public function test_calculator_groups_responses_by_department_from_response_data(): void
    {
        $fx = $this->createSurveyFixture([
            'min_responses_for_breakdown' => 1,
        ]);

        $dept = Department::query()->create([
            'company_id' => $fx->company->id,
            'name' => 'Financeiro',
        ]);

        SurveyResponse::query()->create([
            'survey_id' => $fx->survey->id,
            'session_token' => 'abc',
            'department_id' => $dept->id,
            'completed_at' => now(),
        ])->answers()->create([
            'survey_template_question_id' => $fx->question->id,
            'value' => 5,
        ]);

        app(SurveyResultCalculator::class)->recalculate($fx->survey->fresh());

        $presented = SurveyResultsPresenter::forSurvey($fx->survey->fresh());

        $this->assertCount(1, $presented['deptOveralls']);
        $this->assertSame($dept->id, $presented['deptOveralls'][0]['department_id']);
    }
}
