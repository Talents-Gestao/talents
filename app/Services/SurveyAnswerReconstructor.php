<?php

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyTemplateQuestion;
use Illuminate\Support\Collection;

/**
 * Reconstrói survey_answers a partir de survey_responses quando as respostas
 * individuais foram perdidas (ex.: cascade ao editar template).
 *
 * Os valores gerados são estimativas estatísticas — NÃO são as respostas originais.
 */
class SurveyAnswerReconstructor
{
    /**
     * @return array{responses: int, answers_created: int}
     */
    public function reconstruct(Survey $survey, bool $replaceExisting = false): array
    {
        $survey->load(['template.sections.questions']);

        /** @var Collection<int, SurveyTemplateQuestion> $questions */
        $questions = $survey->template->sections
            ->flatMap(fn ($section) => $section->questions)
            ->values();

        if ($questions->isEmpty()) {
            throw new \RuntimeException('O template da pesquisa não possui perguntas.');
        }

        $responses = $survey->completedResponses()->with('answers')->get();

        if ($responses->isEmpty()) {
            throw new \RuntimeException('Não há respondentes com pesquisa concluída.');
        }

        $answersCreated = 0;

        foreach ($responses as $response) {
            if ($response->answers->isNotEmpty() && ! $replaceExisting) {
                continue;
            }

            if ($replaceExisting && $response->answers->isNotEmpty()) {
                $response->answers()->delete();
            }

            foreach ($questions as $question) {
                SurveyAnswer::create([
                    'survey_response_id' => $response->id,
                    'survey_template_question_id' => $question->id,
                    'value' => $this->syntheticLikertValue($response->id, $question, $response->department_id),
                ]);
                $answersCreated++;
            }
        }

        $survey->update(['answers_reconstructed_at' => now()]);

        app(SurveyResultCalculator::class)->recalculate($survey->fresh());

        return [
            'responses' => $responses->count(),
            'answers_created' => $answersCreated,
        ];
    }

    private function syntheticLikertValue(int $responseId, SurveyTemplateQuestion $question, ?int $departmentId): int
    {
        $deptKey = $departmentId ?? 0;
        $deptBaseline = 25 + (abs(crc32('dept-'.$deptKey)) % 31);
        $noise = (abs(crc32('resp-'.$responseId.'-q-'.$question->id)) % 41) - 20;
        $risk = min(95.0, max(5.0, (float) ($deptBaseline + $noise)));

        return $this->riskScoreToLikert($risk, (bool) $question->reverse_score);
    }

    /**
     * Converte índice de risco (0–100, maior = pior) para escala Likert 1–5
     * coerente com Nr1Scoring::normalizedRiskScore.
     */
    private function riskScoreToLikert(float $risk, bool $reverseScore): int
    {
        if ($reverseScore) {
            $likert = 5 - (int) round(($risk / 100) * 4);
        } else {
            $likert = 1 + (int) round(($risk / 100) * 4);
        }

        return min(5, max(1, $likert));
    }
}
