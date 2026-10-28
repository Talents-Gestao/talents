<?php

namespace Tests\Unit;

use App\Models\Department;
use App\Models\SurveyAnswer;
use App\Models\SurveyResponse;
use App\Models\SurveyTemplateQuestion;
use App\Services\SurveyResultCalculator;
use App\Services\SurveyResultsPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

class SurveyResultsPresenterTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    public function test_question_distributions_count_votes_per_option(): void
    {
        $fx = $this->createSurveyFixture();

        $responseA = SurveyResponse::query()->create([
            'survey_id' => $fx->survey->id,
            'session_token' => 'token-a',
            'completed_at' => now(),
        ]);

        $responseB = SurveyResponse::query()->create([
            'survey_id' => $fx->survey->id,
            'session_token' => 'token-b',
            'completed_at' => now(),
        ]);

        SurveyAnswer::query()->create([
            'survey_response_id' => $responseA->id,
            'survey_template_question_id' => $fx->question->id,
            'value' => 4,
        ]);

        SurveyAnswer::query()->create([
            'survey_response_id' => $responseB->id,
            'survey_template_question_id' => $fx->question->id,
            'value' => 5,
        ]);

        $presented = SurveyResultsPresenter::forSurvey($fx->survey->fresh());

        $this->assertArrayHasKey('questionDistributions', $presented);
        $this->assertCount(1, $presented['questionDistributions']);

        $section = $presented['questionDistributions'][0];
        $this->assertSame($fx->section->id, $section['section_id']);
        $this->assertSame('Seção', $section['section_title']);
        $this->assertCount(1, $section['questions']);

        $question = $section['questions'][0];
        $this->assertSame($fx->question->id, $question['id']);
        $this->assertSame(2, $question['total']);
        $this->assertSame([
            1 => 0,
            2 => 0,
            3 => 0,
            4 => 1,
            5 => 1,
        ], $question['counts']);
    }

    public function test_question_distributions_ignores_incomplete_responses(): void
    {
        $fx = $this->createSurveyFixture();

        $incomplete = SurveyResponse::query()->create([
            'survey_id' => $fx->survey->id,
            'session_token' => 'token-incomplete',
            'completed_at' => null,
        ]);

        SurveyAnswer::query()->create([
            'survey_response_id' => $incomplete->id,
            'survey_template_question_id' => $fx->question->id,
            'value' => 1,
        ]);

        $presented = SurveyResultsPresenter::forSurvey($fx->survey->fresh());
        $question = $presented['questionDistributions'][0]['questions'][0];

        $this->assertSame(0, $question['total']);
        $this->assertSame([
            1 => 0,
            2 => 0,
            3 => 0,
            4 => 0,
            5 => 0,
        ], $question['counts']);
    }

    public function test_question_distributions_includes_response_scale(): void
    {
        $fx = $this->createSurveyFixture();

        SurveyTemplateQuestion::query()->whereKey($fx->question->id)->update([
            'response_scale' => 'agreement',
        ]);

        $presented = SurveyResultsPresenter::forSurvey($fx->survey->fresh());
        $question = $presented['questionDistributions'][0]['questions'][0];

        $this->assertSame('agreement', $question['response_scale']);
    }

    public function test_question_distributions_by_department_filters_votes_per_sector(): void
    {
        $fx = $this->createSurveyFixture();

        $deptA = Department::query()->create([
            'company_id' => $fx->company->id,
            'name' => 'Operações',
        ]);

        $deptB = Department::query()->create([
            'company_id' => $fx->company->id,
            'name' => 'Financeiro',
        ]);

        // Cada setor tem 5 respondentes (o piso): A vota 5 e B vota 2.
        foreach ([[$deptA, 5], [$deptB, 2]] as [$dept, $value]) {
            for ($i = 0; $i < 5; $i++) {
                SurveyResponse::query()->create([
                    'survey_id' => $fx->survey->id,
                    'session_token' => "token-{$dept->id}-{$i}",
                    'department_id' => $dept->id,
                    'completed_at' => now(),
                ])->answers()->create([
                    'survey_template_question_id' => $fx->question->id,
                    'value' => $value,
                ]);
            }
        }

        app(SurveyResultCalculator::class)->recalculate($fx->survey->fresh());

        $presented = SurveyResultsPresenter::forSurvey($fx->survey->fresh());

        $this->assertCount(2, $presented['questionDistributionsByDepartment']);

        $ops = collect($presented['questionDistributionsByDepartment'])->firstWhere('department_id', $deptA->id);
        $fin = collect($presented['questionDistributionsByDepartment'])->firstWhere('department_id', $deptB->id);

        $this->assertSame(5, $ops['sections'][0]['questions'][0]['counts'][5]);
        $this->assertSame(0, $ops['sections'][0]['questions'][0]['counts'][2]);
        $this->assertSame(5, $fin['sections'][0]['questions'][0]['counts'][2]);
        $this->assertSame(0, $fin['sections'][0]['questions'][0]['counts'][5]);
    }

    public function test_question_distributions_by_department_hide_sectors_below_the_floor(): void
    {
        $fx = $this->createSurveyFixture();
        $small = Department::query()->create(['company_id' => $fx->company->id, 'name' => 'Pequeno']);

        for ($i = 0; $i < 2; $i++) {
            SurveyResponse::query()->create([
                'survey_id' => $fx->survey->id,
                'session_token' => "small-{$i}",
                'department_id' => $small->id,
                'completed_at' => now(),
            ])->answers()->create(['survey_template_question_id' => $fx->question->id, 'value' => 5]);
        }

        app(SurveyResultCalculator::class)->recalculate($fx->survey->fresh());
        $presented = SurveyResultsPresenter::forSurvey($fx->survey->fresh());

        $this->assertSame([], $presented['questionDistributionsByDepartment']);
        $this->assertSame([], $presented['departmentParticipation']);
        // As respostas do setor pequeno continuam no geral.
        $this->assertSame(2, $presented['questionDistributions'][0]['questions'][0]['counts'][5]);
    }
}
