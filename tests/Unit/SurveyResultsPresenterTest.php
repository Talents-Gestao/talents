<?php

namespace Tests\Unit;

use App\Models\Department;
use App\Models\SurveyAnswer;
use App\Models\SurveyResponse;
use App\Models\SurveyTemplateQuestion;
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

        $responseA = SurveyResponse::query()->create([
            'survey_id' => $fx->survey->id,
            'session_token' => 'token-a',
            'department_id' => $deptA->id,
            'completed_at' => now(),
        ]);

        $responseB = SurveyResponse::query()->create([
            'survey_id' => $fx->survey->id,
            'session_token' => 'token-b',
            'department_id' => $deptB->id,
            'completed_at' => now(),
        ]);

        SurveyAnswer::query()->create([
            'survey_response_id' => $responseA->id,
            'survey_template_question_id' => $fx->question->id,
            'value' => 5,
        ]);

        SurveyAnswer::query()->create([
            'survey_response_id' => $responseB->id,
            'survey_template_question_id' => $fx->question->id,
            'value' => 2,
        ]);

        $presented = SurveyResultsPresenter::forSurvey($fx->survey->fresh());

        $this->assertCount(2, $presented['questionDistributionsByDepartment']);

        $ops = collect($presented['questionDistributionsByDepartment'])->firstWhere('department_id', $deptA->id);
        $fin = collect($presented['questionDistributionsByDepartment'])->firstWhere('department_id', $deptB->id);

        $this->assertSame(1, $ops['sections'][0]['questions'][0]['counts'][5]);
        $this->assertSame(0, $ops['sections'][0]['questions'][0]['counts'][2]);
        $this->assertSame(1, $fin['sections'][0]['questions'][0]['counts'][2]);
        $this->assertSame(0, $fin['sections'][0]['questions'][0]['counts'][5]);
    }
}
