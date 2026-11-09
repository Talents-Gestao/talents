<?php

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyGroupResult;
use App\Models\SurveyResult;
use App\Support\Nr1BandLabels;
use App\Support\Nr1GroupPrivacy;

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

        // Só a exibição é suprimida: o índice geral e as dimensões continuam usando todas as respostas.
        $visibleDepartments = Nr1GroupPrivacy::publishableDepartmentIdsForSurvey($survey);
        $othersOverall = self::othersOverall($survey);

        $bySection = $results->filter(fn ($r) => $r->survey_template_section_id !== null && $r->department_id === null)->values();

        $deptOveralls = $results
            ->filter(fn ($r) => $r->department_id !== null && $r->survey_template_section_id === null
                && in_array((int) $r->department_id, $visibleDepartments, true))
            ->values();

        $deptSectionResults = $results
            ->filter(fn ($r) => $r->department_id !== null && $r->survey_template_section_id !== null
                && in_array((int) $r->department_id, $visibleDepartments, true));

        $deptSectionsByDepartment = $deptSectionResults
            ->groupBy('department_id')
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'department_id' => $first->department_id,
                    'department_name' => $first->department?->name ?? 'Setor #'.$first->department_id,
                    'is_others' => false,
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

        $departmentParticipation = array_values(array_filter(
            self::buildDepartmentParticipation($survey),
            fn (array $participation) => in_array($participation['department_id'], $visibleDepartments, true),
        ));

        if ($othersOverall !== null) {
            $departmentParticipation[] = [
                'department_id' => null,
                'department_name' => PsychosocialDashboardBuilder::OTHERS_LABEL,
                'respondent_count' => (int) $othersOverall->respondent_count,
                'meets_minimum' => true,
                'is_others' => true,
            ];

            $deptOveralls = $deptOveralls->concat([(object) [
                'id' => $othersOverall->id,
                'average_score' => (float) $othersOverall->average_score,
                'risk_level' => $othersOverall->risk_level,
                'respondent_count' => (int) $othersOverall->respondent_count,
                'department_id' => null,
                'department' => null,
                'is_others' => true,
            ]])->values();

            $othersSections = self::othersSectionRows($survey);
            if ($othersSections !== []) {
                $deptSectionsByDepartment = $deptSectionsByDepartment
                    ->concat([[
                        'department_id' => null,
                        'department_name' => PsychosocialDashboardBuilder::OTHERS_LABEL,
                        'is_others' => true,
                        'sections' => $othersSections,
                    ]])
                    ->values();
            }
        }

        $publishableParticipation = array_values(array_filter(
            $departmentParticipation,
            fn (array $participation) => ($participation['is_others'] ?? false) === false,
        ));

        $questionDistributionsByDepartment = array_map(
            fn (array $participation) => [
                'department_id' => $participation['department_id'],
                'department_name' => $participation['department_name'],
                'respondent_count' => $participation['respondent_count'],
                'sections' => self::buildQuestionDistributions($survey, $participation['department_id']),
            ],
            $publishableParticipation
        );

        return [
            'survey' => $survey,
            'bandLabels' => Nr1BandLabels::forSurvey($survey),
            'overall' => $overall,
            'bySection' => $bySection,
            'deptOveralls' => $deptOveralls->map(function ($r) {
                $isOthers = (bool) ($r->is_others ?? false);

                return [
                    'id' => $r->id,
                    'average_score' => (float) $r->average_score,
                    'risk_level' => $r->risk_level,
                    'respondent_count' => $r->respondent_count,
                    'department_id' => $isOthers ? null : $r->department_id,
                    'department_name' => $isOthers
                        ? PsychosocialDashboardBuilder::OTHERS_LABEL
                        : ($r->department?->name ?? 'Setor #'.$r->department_id),
                    'is_others' => $isOthers,
                ];
            }),
            'deptSectionsByDepartment' => $deptSectionsByDepartment,
            'insights' => $survey->insights,
            'questionDistributions' => self::buildQuestionDistributions($survey),
            'departmentParticipation' => $departmentParticipation,
            'questionDistributionsByDepartment' => $questionDistributionsByDepartment,
        ];
    }

    /**
     * Agregado «Outros Setores» (geral) quando o grupo atinge o mínimo de privacidade.
     */
    private static function othersOverall(Survey $survey): ?SurveyGroupResult
    {
        $overall = SurveyGroupResult::query()
            ->where('survey_id', $survey->id)
            ->where('group_key', SurveyGroupResult::GROUP_OTHERS)
            ->whereNull('survey_template_section_id')
            ->first();

        if ($overall === null || (int) $overall->respondent_count < Nr1GroupPrivacy::minFor($survey)) {
            return null;
        }

        return $overall;
    }

    /**
     * @return list<array{id: int, survey_template_section_id: int, average_score: float, risk_level: string, respondent_count: int, meta: mixed}>
     */
    private static function othersSectionRows(Survey $survey): array
    {
        return SurveyGroupResult::query()
            ->where('survey_id', $survey->id)
            ->where('group_key', SurveyGroupResult::GROUP_OTHERS)
            ->whereNotNull('survey_template_section_id')
            ->with('section')
            ->orderBy('survey_template_section_id')
            ->get()
            ->map(fn (SurveyGroupResult $r) => [
                'id' => $r->id,
                'survey_template_section_id' => $r->survey_template_section_id,
                'average_score' => (float) $r->average_score,
                'risk_level' => $r->risk_level,
                'respondent_count' => $r->respondent_count,
                'meta' => array_merge($r->meta ?? [], [
                    'section_title' => $r->section?->title,
                ]),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{department_id: int, department_name: string, respondent_count: int, meets_minimum: bool, is_others: bool}>
     */
    private static function buildDepartmentParticipation(Survey $survey): array
    {
        $min = 1;
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
                'is_others' => false,
            ];
        }

        usort($participation, fn (array $a, array $b) => strcmp($a['department_name'], $b['department_name']));

        return $participation;
    }

    /**
     * @return list<array{section_id: int, section_title: string, questions: list<array{id: int, body: string, response_scale: string, reverse_score: bool, total: int, counts: array<int, int>}>}>
     */
    private static function buildQuestionDistributions(Survey $survey, ?int $departmentId = null): array
    {
        $countsByQuestion = SurveyAnswer::query()
            ->selectRaw('survey_answers.survey_template_question_id, survey_answers.value, COUNT(*) as total')
            ->join('survey_responses', 'survey_responses.id', '=', 'survey_answers.survey_response_id')
            ->where('survey_responses.survey_id', $survey->id)
            ->whereNotNull('survey_responses.completed_at')
            ->when($departmentId !== null, fn ($q) => $q->where('survey_responses.department_id', $departmentId))
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
                    // Enunciado favorável: a média de risco inverte (6 − resposta); a UI precisa deste flag.
                    'reverse_score' => (bool) $question->reverse_score,
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
