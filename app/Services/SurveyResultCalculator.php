<?php

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyMethodVersion;
use App\Models\SurveyResult;
use App\Models\SurveyTemplateQuestion;
use App\Support\Nr1PooledItemScore;
use App\Support\Nr1Scoring;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SurveyResultCalculator
{
    /** Método da campanha em cálculo; cada linha gravada registra o método usado. */
    private int $methodVersionId;

    /** Preenchido só quando a campanha aponta para talents39_v1; nulo mantém a fórmula de produção. */
    private ?SurveyMethodVersion $pooledMethod = null;

    public function recalculate(Survey $survey): void
    {
        $survey->load(['template.sections.questions', 'company.departments', 'methodVersion']);

        /** @var Collection<int, SurveyTemplateQuestion> $questionsById */
        $questionsById = $survey->template->sections
            ->flatMap(fn ($s) => $s->questions)
            ->keyBy('id');

        $this->pooledMethod = $survey->methodVersion?->code === SurveyMethodVersion::CODE_TALENTS39_V1
            ? $survey->methodVersion
            : null;
        $this->methodVersionId = $this->pooledMethod?->id
            ?? SurveyMethodVersion::idForCode(SurveyMethodVersion::CODE_PRODUCTION_V1);

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
        if ($this->pooledMethod !== null) {
            $this->storePooledAggregate($survey, $responses, $questionsById, $questionIds, $sectionId, $departmentId);

            return;
        }

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
                $weightedSum += Nr1Scoring::effectiveLikertValue($q, (int) $answer->value) * $weight;
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
            'risk_level' => Nr1Scoring::riskLevel($avg),
            'respondent_count' => count($scores),
            'method_version_id' => $this->methodVersionId,
            'meta' => [
                'section_title' => $sectionTitle,
            ],
        ]);
    }

    /**
     * talents39_v1: cada resposta válida do recorte pesa 1 (a coluna weight é ignorada).
     * respondent_count conta pessoas com ao menos uma resposta válida, não respostas.
     *
     * @param  Collection<int, \App\Models\SurveyResponse>  $responses
     * @param  Collection<int|string, mixed>  $questionIds
     */
    private function storePooledAggregate(
        Survey $survey,
        Collection $responses,
        Collection $questionsById,
        Collection $questionIds,
        ?int $sectionId,
        ?int $departmentId
    ): void {
        $cutQuestionIds = $questionIds->map(fn ($id) => (int) $id)->flip();
        $numerator = 0;
        $denominator = 0;
        $contributors = 0;

        foreach ($responses as $response) {
            $validAnswers = $response->answers->filter(fn ($answer) => $answer->value !== null
                && $cutQuestionIds->has((int) $answer->survey_template_question_id)
                && $questionsById->has($answer->survey_template_question_id));

            if ($validAnswers->isEmpty()) {
                continue;
            }

            $contributors++;
            foreach ($validAnswers as $answer) {
                $numerator += (int) Nr1Scoring::effectiveLikertValue(
                    $questionsById->get($answer->survey_template_question_id),
                    (int) $answer->value,
                );
                $denominator++;
            }
        }

        if ($denominator === 0) {
            return;
        }

        $score = Nr1PooledItemScore::fromSums($numerator, $denominator, $this->pooledMethod);

        SurveyResult::create([
            'survey_id' => $survey->id,
            'survey_template_section_id' => $sectionId,
            'department_id' => $departmentId,
            'average_score' => $score->storedScore(),
            'risk_level' => $score->riskLevel,
            'respondent_count' => $contributors,
            'method_version_id' => $this->methodVersionId,
            'score_numerator' => $numerator,
            'score_denominator' => $denominator,
            'meta' => [
                'section_title' => $sectionId
                    ? $survey->template->sections->firstWhere('id', $sectionId)?->title
                    : null,
            ],
        ]);
    }
}
