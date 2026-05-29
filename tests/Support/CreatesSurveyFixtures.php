<?php

namespace Tests\Support;

use App\Models\Company;
use App\Models\Survey;
use App\Models\SurveyTemplate;
use App\Models\SurveyTemplateQuestion;
use App\Models\SurveyTemplateSection;
use Illuminate\Support\Str;

trait CreatesSurveyFixtures
{
    /**
     * @param  array<string, mixed>  $surveyOverrides
     * @return object{company: Company, template: SurveyTemplate, section: SurveyTemplateSection, question: SurveyTemplateQuestion, survey: Survey}
     */
    protected function createSurveyFixture(array $surveyOverrides = []): object
    {
        $company = Company::query()->create([
            'name' => 'Empresa Fixture',
            'cnpj' => '11.111.111/0001-11',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);

        $this->subscribeCompanyToNr1($company);

        $template = SurveyTemplate::query()->create([
            'title' => 'Template fixture',
            'description' => null,
            'is_active' => true,
        ]);

        $company->surveyTemplates()->attach($template->id);

        $section = SurveyTemplateSection::query()->create([
            'survey_template_id' => $template->id,
            'title' => 'Seção',
            'description' => null,
            'sort_order' => 0,
        ]);

        $question = SurveyTemplateQuestion::query()->create([
            'survey_template_section_id' => $section->id,
            'body' => 'Pergunta teste?',
            'reverse_score' => false,
            'sort_order' => 0,
        ]);

        $survey = Survey::query()->create(array_merge([
            'company_id' => $company->id,
            'survey_template_id' => $template->id,
            'title' => 'Campanha fixture',
            'public_token' => (string) Str::uuid(),
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
            'min_responses_for_breakdown' => 1,
        ], $surveyOverrides));

        return (object) [
            'company' => $company,
            'template' => $template,
            'section' => $section,
            'question' => $question,
            'survey' => $survey,
        ];
    }
}
