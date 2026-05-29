<?php

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyResult;

class SurveyResultsPresenter
{
    /**
     * Dados agregados da pesquisa no mesmo formato usado em Resultados (cliente).
     *
     * @return array<string, mixed>
     */
    public static function forSurvey(Survey $survey): array
    {
        $survey->loadMissing(['template.sections.questions', 'company.departments', 'insights']);

        $results = SurveyResult::query()
            ->where('survey_id', $survey->id)
            ->with(['section', 'department'])
            ->orderBy('survey_template_section_id')
            ->orderBy('department_id')
            ->get();

        $overall = $results->first(fn ($r) => $r->survey_template_section_id === null && $r->department_id === null);

        $bySection = $results->filter(fn ($r) => $r->survey_template_section_id !== null && $r->department_id === null)->values();

        $deptOveralls = $results
            ->filter(fn ($r) => $r->department_id !== null && $r->survey_template_section_id === null)
            ->values();

        $deptSectionResults = $results
            ->filter(fn ($r) => $r->department_id !== null && $r->survey_template_section_id !== null);

        $deptSectionsByDepartment = $deptSectionResults
            ->groupBy('department_id')
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'department_id' => $first->department_id,
                    'department_name' => $first->department?->name ?? 'Setor #'.$first->department_id,
                    'sections' => $rows->values()->map(function ($r) {
                        return [
                            'id' => $r->id,
                            'survey_template_section_id' => $r->survey_template_section_id,
                            'average_score' => (float) $r->average_score,
                            'risk_level' => $r->risk_level,
                            'respondent_count' => $r->respondent_count,
                            'meta' => $r->meta,
                        ];
                    }),
                ];
            })
            ->values();

        return [
            'survey' => $survey,
            'overall' => $overall,
            'bySection' => $bySection,
            'deptOveralls' => $deptOveralls->map(function ($r) {
                return [
                    'id' => $r->id,
                    'average_score' => (float) $r->average_score,
                    'risk_level' => $r->risk_level,
                    'respondent_count' => $r->respondent_count,
                    'department_id' => $r->department_id,
                    'department_name' => $r->department?->name ?? 'Setor #'.$r->department_id,
                ];
            }),
            'deptSectionsByDepartment' => $deptSectionsByDepartment,
            'insights' => $survey->insights,
            'questionDistributions' => self::buildQuestionDistributions($survey),
            'departmentParticipation' => self::buildDepartmentParticipation($survey),
        ];
    }

    /**
     * @return list<array{department_id: int, department_name: string, respondent_count: int, meets_minimum: bool}>
     */
    private static function buildDepartmentParticipation(Survey $survey): array
    {
        $min = (int) ($survey->min_responses_for_breakdown ?? 1);
        $countsByDepartment = $survey->completedResponses()
            ->whereNotNull('department_id')
            ->get(['department_id'])
            ->groupBy(fn ($response) => (int) $response->department_id)
            ->map->count();

        if ($countsByDepartment->isEmpty()) {
            return [];
        }

        $departmentNames = ($survey->company?->departments ?? collect())->keyBy('id');
        $participation = [];

        foreach ($countsByDepartment as $departmentId => $count) {
            $department = $departmentNames->get((int) $departmentId);

            $participation[] = [
                'department_id' => (int) $departmentId,
                'department_name' => $department?->name ?? 'Setor #'.$departmentId,
                'respondent_count' => (int) $count,
                'meets_minimum' => (int) $count >= $min,
            ];
        }

        usort($participation, fn (array $a, array $b) => strcmp($a['department_name'], $b['department_name']));

        return $participation;
    }

    /**
     * @return list<array{section_id: int, section_title: string, questions: list<array{id: int, body: string, response_scale: string, total: int, counts: array<int, int>}>}>
     */
    private static function buildQuestionDistributions(Survey $survey): array
    {
        $countsByQuestion = SurveyAnswer::query()
            ->selectRaw('survey_answers.survey_template_question_id, survey_answers.value, COUNT(*) as total')
            ->join('survey_responses', 'survey_responses.id', '=', 'survey_answers.survey_response_id')
            ->where('survey_responses.survey_id', $survey->id)
            ->whereNotNull('survey_responses.completed_at')
            ->groupBy('survey_answers.survey_template_question_id', 'survey_answers.value')
            ->get()
            ->groupBy('survey_template_question_id')
            ->map(function ($rows) {
                return $rows->mapWithKeys(fn ($row) => [(int) $row->value => (int) $row->total])->all();
            })
            ->all();

        $sections = [];

        foreach ($survey->template?->sections ?? [] as $section) {
            $questions = [];

            foreach ($section->questions as $question) {
                $rawCounts = $countsByQuestion[$question->id] ?? [];
                $counts = [];

                for ($value = 1; $value <= 5; $value++) {
                    $counts[$value] = (int) ($rawCounts[$value] ?? 0);
                }

                $questions[] = [
                    'id' => $question->id,
                    'body' => $question->body,
                    'response_scale' => $question->response_scale ?? 'frequency',
                    'total' => array_sum($counts),
                    'counts' => $counts,
                ];
            }

            if ($questions === []) {
                continue;
            }

            $sections[] = [
                'section_id' => $section->id,
                'section_title' => $section->title,
                'questions' => $questions,
            ];
        }

        return $sections;
    }
}
