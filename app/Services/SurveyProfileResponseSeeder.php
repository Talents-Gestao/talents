<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyResponse;
use App\Models\SurveyTemplateQuestion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Insere respostas completas com perfil favorável (baixo risco) ou desfavorável (alto risco).
 * Uso: demonstração, testes ou substituir lote reconstruído — não substitui respostas reais de colaboradores.
 */
class SurveyProfileResponseSeeder
{
    /**
     * @return array{favorable: int, unfavorable: int, total_answers: int}
     */
    public function seed(
        Survey $survey,
        int $favorableCount,
        int $unfavorableCount,
        bool $replaceExisting = false,
    ): array {
        if ($favorableCount < 0 || $unfavorableCount < 0 || ($favorableCount + $unfavorableCount) === 0) {
            throw new \InvalidArgumentException('Informe pelo menos uma resposta favorável ou desfavorável.');
        }

        $survey->load(['template.sections.questions', 'company']);

        /** @var Collection<int, SurveyTemplateQuestion> $questions */
        $questions = $survey->template->sections
            ->flatMap(fn ($section) => $section->questions)
            ->values();

        if ($questions->isEmpty()) {
            throw new \RuntimeException('O template da pesquisa não possui perguntas.');
        }

        $departmentIds = Department::query()
            ->where('company_id', $survey->company_id)
            ->pluck('id')
            ->all();

        $totalAnswers = 0;

        DB::transaction(function () use (
            $survey,
            $questions,
            $favorableCount,
            $unfavorableCount,
            $replaceExisting,
            $departmentIds,
            &$totalAnswers,
        ) {
            if ($replaceExisting) {
                $responseIds = $survey->responses()->pluck('id');
                SurveyAnswer::query()->whereIn('survey_response_id', $responseIds)->delete();
                $survey->responses()->delete();
                $survey->update(['answers_reconstructed_at' => null]);
            }

            $profiles = array_merge(
                array_fill(0, $favorableCount, 'favorable'),
                array_fill(0, $unfavorableCount, 'unfavorable'),
            );

            foreach ($profiles as $index => $profile) {
                $response = SurveyResponse::create([
                    'survey_id' => $survey->id,
                    'session_token' => Str::random(40),
                    'department_id' => $this->pickDepartmentId($departmentIds, $index),
                    'age_range' => null,
                    'tenure_range' => null,
                    'completed_at' => now(),
                ]);

                foreach ($questions as $question) {
                    SurveyAnswer::create([
                        'survey_response_id' => $response->id,
                        'survey_template_question_id' => $question->id,
                        'value' => $this->likertForProfile($profile, $index, $question),
                    ]);
                    $totalAnswers++;
                }
            }
        });

        app(SurveyResultCalculator::class)->recalculate($survey->fresh());

        return [
            'favorable' => $favorableCount,
            'unfavorable' => $unfavorableCount,
            'total_answers' => $totalAnswers,
        ];
    }

    /**
     * @param  list<int>  $departmentIds
     */
    private function pickDepartmentId(array $departmentIds, int $responseIndex): ?int
    {
        if ($departmentIds === []) {
            return null;
        }

        return (int) $departmentIds[$responseIndex % count($departmentIds)];
    }

    /**
     * Perfil favorável: baixo risco (itens de risco → 1–2; protetores → 4–5).
     * Perfil desfavorável: alto risco (itens de risco → 4–5; protetores → 1–2).
     */
    private function likertForProfile(string $profile, int $responseIndex, SurveyTemplateQuestion $question): int
    {
        $bucket = abs(crc32($profile.'-'.$responseIndex.'-q-'.$question->id)) % 5;

        if ((bool) $question->reverse_score) {
            $values = $profile === 'favorable'
                ? [4, 4, 5, 5, 5]
                : [1, 1, 2, 2, 2];
        } else {
            $values = $profile === 'favorable'
                ? [1, 1, 2, 2, 2]
                : [4, 4, 5, 5, 5];
        }

        return $values[$bucket];
    }
}
