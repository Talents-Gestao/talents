<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Company;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyMethodVersion;
use App\Models\SurveyResponse;
use App\Models\SurveyTemplate;
use App\Services\SurveyResultCalculator;
use App\Support\Nr1InstrumentMatrix;
use Database\Seeders\Talents39InstrumentData;
use Illuminate\Support\Str;

/**
 * Campanha V11 de teste: importa o instrumento de 39 itens e cria os 40 respondentes do Apêndice A.
 * Exige CreatesSurveyFixtures (subscribeCompanyToNr1) no mesmo teste.
 */
trait CreatesV11Campaign
{
    /**
     * @return object{company: Company, template: SurveyTemplate, survey: Survey, questionIds: array<int, int>}
     */
    private function v11Campaign(bool $withResponses = true): object
    {
        $this->artisan('nr1:import-instrument-v11')->assertSuccessful();
        $template = SurveyTemplate::query()->where('title', Talents39InstrumentData::TEMPLATE_TITLE)->firstOrFail();

        $company = Company::query()->create([
            'name' => 'Empresa V11',
            'cnpj' => '22.222.222/0001-22',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);
        $this->subscribeCompanyToNr1($company);
        $company->surveyTemplates()->attach($template->id);

        $survey = Survey::query()->create([
            'company_id' => $company->id,
            'survey_template_id' => $template->id,
            'title' => 'Campanha V11',
            'public_token' => (string) Str::uuid(),
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
            'method_version_id' => SurveyMethodVersion::idForCode(SurveyMethodVersion::CODE_TALENTS39_V1),
        ]);

        $questionIds = [];
        foreach (Nr1InstrumentMatrix::forTemplate((int) $template->id) as $row) {
            $questionIds[$row['item_number']] = $row['question_id'];
        }

        if ($withResponses) {
            $this->seedAppendixResponses($survey, $questionIds);
            app(SurveyResultCalculator::class)->recalculate($survey->fresh());
        }

        return (object) compact('company', 'template', 'survey', 'questionIds');
    }

    /**
     * @param  array<int, int>  $questionIds  item => question_id
     */
    private function seedAppendixResponses(Survey $survey, array $questionIds): void
    {
        $values = [];
        foreach (Nr1V11Dataset::COUNTS as $item => $counts) {
            $list = [];
            foreach ($counts as $index => $count) {
                $list = array_merge($list, array_fill(0, $count, $index + 1));
            }
            $values[$item] = $list;
        }

        for ($respondent = 0; $respondent < Nr1V11Dataset::RESPONDENTS; $respondent++) {
            $response = SurveyResponse::query()->create([
                'survey_id' => $survey->id,
                'session_token' => (string) Str::uuid(),
                'completed_at' => now()->startOfMonth(),
            ]);

            SurveyAnswer::query()->insert(array_map(fn (int $item) => [
                'survey_response_id' => $response->id,
                'survey_template_question_id' => $questionIds[$item],
                'value' => $values[$item][$respondent],
                'created_at' => now(),
                'updated_at' => now(),
            ], array_keys($questionIds)));
        }
    }
}
