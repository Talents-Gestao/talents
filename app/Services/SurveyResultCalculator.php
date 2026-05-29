<?php

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyResult;
use App\Models\SurveyTemplateQuestion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SurveyResultCalculator
{
    public function recalculate(Survey $survey): void
    {
        $survey->load(['template.sections.questions', 'company.departments']);

        /** @var Collection<int, SurveyTemplateQuestion> $questionsById */
        $questionsById = $survey->template->sections
            ->flatMap(fn ($s) => $s->questions)
            ->keyBy('id');

        DB::transaction(function () use ($survey, $questionsById) {
            $survey->results()->delete();

            $responses = $survey->completedResponses()->with(['answers'])->get();

            if ($responses->isEmpty()) {
                return;
            }

            $min = 1;

            $allQuestionIds = $questionsById->keys();

            $this->storeAggregate($survey, $responses, $questionsById, $allQuestionIds, null, null);

            foreach ($survey->template->sections as $section) {
                $qids = $section->questions->pluck('id');
                if ($qids->isEmpty()) {
                    continue;
                }
                $this->storeAggregate($survey, $responses, $questionsById, $qids, $section->id, null);
            }

            $responsesByDepartment = $responses
                ->filter(fn ($response) => $response->department_id !== null)
                ->groupBy(fn ($response) => (int) $response->department_id);

            foreach ($responsesByDepartment as $deptId => $deptResponses) {
                if ($deptResponses->count() < $min) {
                    continue;
                }
                $this->storeAggregate($survey, $deptResponses, $questionsById, $allQuestionIds, null, (int) $deptId);
                foreach ($survey->template->sections as $section) {
                    $qids = $section->questions->pluck('id');
                    if ($qids->isEmpty()) {
                        continue;
                    }
                    $this->storeAggregate($survey, $deptResponses, $questionsById, $qids, $section->id, (int) $deptId);
                }
            }
        });

        app(InsightGenerator::class)->generateForSurvey($survey->fresh());
    }

    /**
     * @param  Collection<int, \App\Models\SurveyResponse>  $responses
     * @param  Collection<int|string, mixed>  $questionIds
     */
    private function storeAggregate(
        Survey $survey,
        Collection $responses,
        Collection $questionsById,
        Collection $questionIds,
        ?int $sectionId,
        ?int $departmentId
    ): void {
        $scores = [];
        foreach ($responses as $response) {
            $weightedSum = 0.0;
            $totalWeight = 0.0;
            foreach ($questionIds as $qid) {
                $answer = $response->answers->firstWhere('survey_template_question_id', $qid);
                if (! $answer) {
                    continue;
                }
                /** @var SurveyTemplateQuestion|null $q */
                $q = $questionsById->get($qid);
                if (! $q) {
                    continue;
                }
                $weight = max(0.01, (float) ($q->weight ?? 1.0));
                $weightedSum += $this->normalizedHealthScore($q, (int) $answer->value) * $weight;
                $totalWeight += $weight;
            }
            if ($totalWeight > 0) {
                $scores[] = $weightedSum / $totalWeight;
            }
        }

        if (empty($scores)) {
            return;
        }

        $avg = array_sum($scores) / count($scores);

        $sectionTitle = null;
        if ($sectionId) {
            $sectionTitle = $survey->template->sections->firstWhere('id', $sectionId)?->title;
        }

        SurveyResult::create([
            'survey_id' => $survey->id,
            'survey_template_section_id' => $sectionId,
            'department_id' => $departmentId,
            'average_score' => round($avg, 3),
            'risk_level' => $this->healthLevel($avg),
            'respondent_count' => count($scores),
            'meta' => [
                'section_title' => $sectionTitle,
            ],
        ]);
    }

    /**
     * Score de saúde (0 = pior, 100 = melhor), a partir da escala Likert 1–5.
     */
    private function normalizedHealthScore(SurveyTemplateQuestion $question, int $likert): float
    {
        $v = $question->reverse_score ? (6 - $likert) : $likert;

        return 100 - ((($v - 1) / 4) * 100);
    }

    /**
     * Faixas de saúde: verde ≥67, amarelo 34–66, vermelho ≤33.
     */
    private function healthLevel(float $score): string
    {
        if ($score >= 67) {
            return 'green';
        }
        if ($score >= 34) {
            return 'yellow';
        }

        return 'red';
    }
}
