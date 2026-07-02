<?php

namespace Tests\Support;

use App\Models\SurveyResult;

trait SeedsNr1SurveyResults
{
    /**
     * @param  object{survey: \App\Models\Survey, section: \App\Models\SurveyTemplateSection}  $fx
     */
    protected function seedNr1OverallAndSectionResult(
        object $fx,
        string $riskLevel,
        float $score = 4.0,
        ?string $sectionTitle = null,
    ): void {
        SurveyResult::query()->create([
            'survey_id' => $fx->survey->id,
            'survey_template_section_id' => null,
            'department_id' => null,
            'average_score' => $score,
            'risk_level' => $riskLevel,
            'respondent_count' => 10,
            'meta' => [],
        ]);

        SurveyResult::query()->create([
            'survey_id' => $fx->survey->id,
            'survey_template_section_id' => $fx->section->id,
            'department_id' => null,
            'average_score' => $score,
            'risk_level' => $riskLevel,
            'respondent_count' => 10,
            'meta' => ['section_title' => $sectionTitle ?? $fx->section->title],
        ]);
    }
}
