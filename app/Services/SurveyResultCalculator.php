<?php

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyGroupResult;
use App\Models\SurveyMethodVersion;
use App\Models\SurveyResult;
use App\Models\SurveyTemplateQuestion;
use App\Support\Nr1GroupPrivacy;
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
            $survey->groupResults()->delete();

            $cuts = $this->cuts($survey, $questionsById);
            $profiles = $this->profiles($survey, $questionsById, $cuts);

            if ($profiles === []) {
                return;
            }

            $min = 1;

            $this->storeGroup($survey, $profiles, $cuts, null, null);

            $profilesByDepartment = [];
            foreach ($profiles as $profile) {
                if ($profile['department_id'] !== null) {
                    $profilesByDepartment[$profile['department_id']][] = $profile;
                }
            }

            foreach ($profilesByDepartment as $deptId => $deptProfiles) {
                if (count($deptProfiles) < $min) {
                    continue;
                }
                $this->storeGroup($survey, $deptProfiles, $cuts, (int) $deptId, null);
            }

            $this->storeOthersGroup($survey, $profiles, $cuts);
        });

        app(InsightGenerator::class)->generateForSurvey($survey->fresh());
    }

    /**
     * Recortes de cálculo: o 0 é a campanha inteira; os demais, uma seção cada (na ordem do template).
     *
     * @param  Collection<int, SurveyTemplateQuestion>  $questionsById
     * @return list<array{section_id: int|null, title: string|null, question_ids: list<int>}>
     */
    private function cuts(Survey $survey, Collection $questionsById): array
    {
        $cuts = [['section_id' => null, 'title' => null, 'question_ids' => $questionsById->keys()->map(fn ($id) => (int) $id)->all()]];

        foreach ($survey->template->sections as $section) {
            $ids = $section->questions->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($ids !== []) {
                $cuts[] = ['section_id' => (int) $section->id, 'title' => $section->title, 'question_ids' => $ids];
            }
        }

        return $cuts;
    }

    /**
     * Uma passada por consulta que traz só valores (sem hidratar models). Cada resposta concluída vira um perfil com a
     * contribuição já calculada em cada recorte; os setores, o grupo «Outros» e a campanha inteira só somam perfis.
     * Ordem e fórmulas são as do cálculo anterior (respostas por id, perguntas na ordem do recorte).
     *
     * @param  Collection<int, SurveyTemplateQuestion>  $questionsById
     * @param  list<array{section_id: int|null, title: string|null, question_ids: list<int>}>  $cuts
     * @return list<array{department_id: int|null, cuts: list<mixed>}>
     */
    private function profiles(Survey $survey, Collection $questionsById, array $cuts): array
    {
        $departments = $survey->completedResponses()->orderBy('id')->pluck('department_id', 'id');

        if ($departments->isEmpty()) {
            return [];
        }

        $questions = [];
        foreach ($questionsById as $id => $question) {
            $questions[(int) $id] = ['question' => $question, 'weight' => max(0.01, (float) ($question->weight ?? 1.0)), 'effective' => []];
        }

        $rows = DB::table('survey_answers as a')
            ->join('survey_responses as r', 'r.id', '=', 'a.survey_response_id')
            ->where('r.survey_id', $survey->id)
            ->whereNotNull('r.completed_at')
            ->orderBy('a.survey_response_id')
            ->orderBy('a.id')
            ->select(['a.survey_response_id as response_id', 'a.survey_template_question_id as question_id', 'a.value'])
            ->cursor();

        $computed = [];
        $values = [];
        $currentId = null;

        $flush = function () use (&$values, &$currentId, &$computed, &$questions, $cuts): void {
            if ($currentId !== null) {
                $computed[$currentId] = $this->contributions($values, $questions, $cuts);
                $values = [];
            }
        };

        foreach ($rows as $row) {
            if ($row->response_id !== $currentId) {
                $flush();
                $currentId = $row->response_id;
            }
            $values[(int) $row->question_id] = $row->value;
        }
        $flush();

        // Mesma ordem do cálculo anterior: respostas concluídas por id (as sem nenhuma resposta não contribuem).
        $profiles = [];
        foreach ($departments as $responseId => $department) {
            if (isset($computed[$responseId])) {
                $profiles[] = ['department_id' => $department === null ? null : (int) $department, 'cuts' => $computed[$responseId]];
            }
        }

        return $profiles;
    }

    /**
     * Contribuição de UMA resposta em cada recorte: produção = nota ponderada da pessoa (ou null); talents39_v1 =
     * [soma efetiva, nº de respostas válidas] (cada resposta pesa 1; a coluna weight é ignorada).
     *
     * @param  array<int, mixed>  $values  question_id => valor
     * @param  array<int, array{question: SurveyTemplateQuestion, weight: float, effective: array<int, float>}>  $questions
     * @param  list<array{section_id: int|null, title: string|null, question_ids: list<int>}>  $cuts
     * @return list<mixed>
     */
    private function contributions(array $values, array &$questions, array $cuts): array
    {
        $out = [];

        foreach ($cuts as $cut) {
            if ($this->pooledMethod !== null) {
                $numerator = 0;
                $denominator = 0;
                foreach ($cut['question_ids'] as $qid) {
                    if (! isset($values[$qid]) || ! isset($questions[$qid])) {
                        continue;
                    }
                    $numerator += (int) $this->effective($questions[$qid], (int) $values[$qid]);
                    $denominator++;
                }
                $out[] = [$numerator, $denominator];

                continue;
            }

            $weightedSum = 0.0;
            $totalWeight = 0.0;
            foreach ($cut['question_ids'] as $qid) {
                if (! array_key_exists($qid, $values) || ! isset($questions[$qid])) {
                    continue;
                }
                $weightedSum += $this->effective($questions[$qid], (int) $values[$qid]) * $questions[$qid]['weight'];
                $totalWeight += $questions[$qid]['weight'];
            }
            $out[] = $totalWeight > 0 ? $weightedSum / $totalWeight : null;
        }

        return $out;
    }

    /**
     * Valor efetivo (com a inversão do item) de uma resposta, vindo sempre de Nr1Scoring; memoizado por pergunta e valor
     * (são 39 x 5 chamadas em vez de uma por resposta gravada).
     *
     * @param  array{question: SurveyTemplateQuestion, weight: float, effective: array<int, float>}  $question
     */
    private function effective(array &$question, int $value): float
    {
        return $question['effective'][$value] ??= Nr1Scoring::effectiveLikertValue($question['question'], $value);
    }

    /**
     * Grava a campanha inteira e cada seção para um conjunto de perfis (campanha, setor ou grupo «Outros»).
     *
     * @param  list<array{department_id: int|null, cuts: list<mixed>}>  $group
     * @param  list<array{section_id: int|null, title: string|null, question_ids: list<int>}>  $cuts
     */
    private function storeGroup(Survey $survey, array $group, array $cuts, ?int $departmentId, ?string $groupKey): void
    {
        foreach ($cuts as $index => $cut) {
            $this->storeAggregate($survey, $group, $index, $cut, $departmentId, $groupKey);
        }
    }

    /**
     * @param  list<array{department_id: int|null, cuts: list<mixed>}>  $group
     * @param  array{section_id: int|null, title: string|null, question_ids: list<int>}  $cut
     */
    private function storeAggregate(Survey $survey, array $group, int $cutIndex, array $cut, ?int $departmentId, ?string $groupKey): void
    {
        if ($this->pooledMethod !== null) {
            $this->storePooledAggregate($survey, $group, $cutIndex, $cut, $departmentId, $groupKey);

            return;
        }

        $scores = [];
        foreach ($group as $profile) {
            $score = $profile['cuts'][$cutIndex];
            if ($score !== null) {
                $scores[] = $score;
            }
        }

        if (empty($scores)) {
            return;
        }

        $avg = array_sum($scores) / count($scores);

        $this->persistRow([
            'survey_id' => $survey->id,
            'survey_template_section_id' => $cut['section_id'],
            'department_id' => $departmentId,
            'average_score' => round($avg, 3),
            'risk_level' => Nr1Scoring::riskLevel($avg),
            'respondent_count' => count($scores),
            'method_version_id' => $this->methodVersionId,
            'meta' => [
                'section_title' => $cut['title'],
            ],
        ], $groupKey);
    }

    /**
     * talents39_v1: cada resposta válida do recorte pesa 1 (a coluna weight é ignorada).
     * respondent_count conta pessoas com ao menos uma resposta válida, não respostas.
     *
     * @param  list<array{department_id: int|null, cuts: list<mixed>}>  $group
     * @param  array{section_id: int|null, title: string|null, question_ids: list<int>}  $cut
     */
    private function storePooledAggregate(Survey $survey, array $group, int $cutIndex, array $cut, ?int $departmentId, ?string $groupKey): void
    {
        $numerator = 0;
        $denominator = 0;
        $contributors = 0;

        foreach ($group as $profile) {
            [$sum, $count] = $profile['cuts'][$cutIndex];
            if ($count === 0) {
                continue;
            }
            $contributors++;
            $numerator += $sum;
            $denominator += $count;
        }

        if ($denominator === 0) {
            return;
        }

        $score = Nr1PooledItemScore::fromSums($numerator, $denominator, $this->pooledMethod);

        $this->persistRow([
            'survey_id' => $survey->id,
            'survey_template_section_id' => $cut['section_id'],
            'department_id' => $departmentId,
            'average_score' => $score->storedScore(),
            'risk_level' => $score->riskLevel,
            'respondent_count' => $contributors,
            'method_version_id' => $this->methodVersionId,
            'score_numerator' => $numerator,
            'score_denominator' => $denominator,
            'meta' => [
                'section_title' => $cut['title'],
            ],
        ], $groupKey);
    }

    /**
     * Grava a linha de um recorte: geral/seção/setor em survey_results; grupo agregado em
     * survey_group_results (sem department_id).
     *
     * @param  array<string, mixed>  $row
     */
    private function persistRow(array $row, ?string $groupKey): void
    {
        if ($groupKey === null) {
            SurveyResult::create($row);

            return;
        }

        unset($row['department_id']);
        SurveyGroupResult::create($row + ['group_key' => $groupKey]);
    }

    /**
     * "Outros Setores": as respostas dos setores que a política de grupo não publica (menores que o mínimo
     * ou absorvidos pela cascata) entram juntas e a média sai das respostas ORIGINAIS, nunca de média de
     * médias. Nenhuma resposta é descartada; a exibição é suprimida na leitura se o agregado ficar abaixo do mínimo.
     *
     * @param  list<array{department_id: int|null, cuts: list<mixed>}>  $profiles
     * @param  list<array{section_id: int|null, title: string|null, question_ids: list<int>}>  $cuts
     */
    private function storeOthersGroup(Survey $survey, array $profiles, array $cuts): void
    {
        $counts = SurveyResult::query()
            ->where('survey_id', $survey->id)
            ->whereNotNull('department_id')
            ->whereNull('survey_template_section_id')
            ->pluck('respondent_count', 'department_id')
            ->map(fn ($n) => (int) $n)
            ->all();

        if ($counts === []) {
            return;
        }

        $visible = Nr1GroupPrivacy::publishableDepartmentIds($counts, Nr1GroupPrivacy::minFor($survey));
        $hidden = array_values(array_diff(array_keys($counts), $visible));

        if ($hidden === []) {
            return;
        }

        $others = array_values(array_filter($profiles, fn (array $profile) => in_array($profile['department_id'], $hidden, true)));

        if ($others === []) {
            return;
        }

        $this->storeGroup($survey, $others, $cuts, null, SurveyGroupResult::GROUP_OTHERS);
    }
}
