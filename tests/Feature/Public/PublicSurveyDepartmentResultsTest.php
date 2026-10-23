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

    public function test_submit_stores_department_but_small_sector_is_not_shown(): void
    {
        $fx = $this->createSurveyFixture();

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

        // 1 resposta < piso 5: o setor fica gravado, mas não é exibido.
        $this->assertEmpty($presented['deptOveralls']);
        $this->assertEmpty($presented['departmentParticipation']);
        $this->assertNotNull($presented['overall']);
    }

    public function test_sector_at_the_floor_is_shown(): void
    {
        $fx = $this->createSurveyFixture();

        $dept = Department::query()->create([
            'company_id' => $fx->company->id,
            'name' => 'Operações',
        ]);

        for ($i = 0; $i < 5; $i++) {
            SurveyResponse::query()->create([
                'survey_id' => $fx->survey->id,
                'session_token' => 'abc'.$i,
                'department_id' => $dept->id,
                'completed_at' => now(),
            ])->answers()->create([
                'survey_template_question_id' => $fx->question->id,
                'value' => 4,
            ]);
        }

        app(SurveyResultCalculator::class)->recalculate($fx->survey->fresh());

        $presented = SurveyResultsPresenter::forSurvey($fx->survey->fresh());

        $this->assertCount(1, $presented['departmentParticipation']);
        $this->assertTrue($presented['departmentParticipation'][0]['meets_minimum']);
        $this->assertCount(1, $presented['deptOveralls']);
        $this->assertSame($dept->id, $presented['deptOveralls'][0]['department_id']);
    }

    public function test_calculator_still_groups_every_response_by_department(): void
    {
        $fx = $this->createSurveyFixture();

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

        // O cálculo não descarta resposta nenhuma; só a exibição é suprimida.
        $this->assertSame(1, $fx->survey->results()->where('department_id', $dept->id)->whereNull('survey_template_section_id')->count());
        $this->assertEmpty(SurveyResultsPresenter::forSurvey($fx->survey->fresh())['deptOveralls']);
    }
}
