<?php

namespace App\Support;

use App\Models\Survey;
use App\Models\SurveyResult;

class Nr1RiskScenarioResolver
{
    /**
     * @return 'green'|'yellow'|'red'|null
     */
    public static function forSurvey(Survey $survey): ?string
    {
        $overall = SurveyResult::query()
            ->where('survey_id', $survey->id)
            ->whereNull('survey_template_section_id')
            ->whereNull('department_id')
            ->first();

        return self::normalize($overall?->risk_level);
    }

    /**
     * @return 'green'|'yellow'|'red'|null
     */
    public static function normalize(?string $riskLevel): ?string
    {
        if (! in_array($riskLevel, ['green', 'yellow', 'red'], true)) {
            return null;
        }

        return $riskLevel;
    }

    /**
     * @return array<string, mixed>
     */
    public static function scenarioConfig(?string $scenario): array
    {
        $scenario = self::normalize($scenario) ?? 'green';

        return config("nr1_reports.scenarios.{$scenario}", []);
    }

    /**
     * @return array{scenario: string|null, config: array<string, mixed>}
     */
    public static function resolve(Survey $survey): array
    {
        $scenario = self::forSurvey($survey);

        return [
            'scenario' => $scenario,
            'config' => $scenario !== null ? self::scenarioConfig($scenario) : [],
        ];
    }
}
