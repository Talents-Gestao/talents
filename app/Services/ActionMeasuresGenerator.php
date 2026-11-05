<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiAnalysis;
use App\Models\AiSetting;
use App\Models\PsychosocialMeasure;
use App\Models\Survey;
use App\Models\User;
use App\Support\Nr1GroupPrivacy;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Gera medidas do plano a partir dos resultados agregados (uma chamada de IA).
 * Só substitui sugestões ainda «A aprovar»; medidas manuais e já avançadas permanecem.
 */
final class ActionMeasuresGenerator
{
    public function __construct(
        private readonly Nr1AiAnalyzer $analyzer,
        private readonly PsychosocialMeasureService $measures,
    ) {}

    public static function pendingCacheKey(int $surveyId): string
    {
        return Nr1AiAnalyzer::pendingCacheKey($surveyId, Nr1AiAnalyzer::TYPE_NR1_ACTION_MEASURES);
    }

    public function generate(Survey $survey, AiSetting $setting, ?User $actor = null): int
    {
        if (! Nr1GroupPrivacy::hasMinimumSample($survey) || ! $survey->results()->exists()) {
            $survey->forceFill(['action_measures_generated_at' => now()])->save();

            return 0;
        }

        $result = $this->analyzer->generateNarrative(
            $survey,
            $setting,
            Nr1AiAnalyzer::TYPE_NR1_ACTION_MEASURES,
        );

        $rows = $this->parseMeasures($result['content']);
        if ($rows === []) {
            throw new RuntimeException('A IA não devolveu medidas utilizáveis.');
        }

        DB::transaction(function () use ($survey, $actor, $rows, $result): void {
            PsychosocialMeasure::query()
                ->where('survey_id', $survey->id)
                ->where('origin', PsychosocialMeasure::ORIGIN_ENGINE_SUGGESTION)
                ->where('status', PsychosocialMeasure::STATUS_TO_APPROVE)
                ->delete();

            foreach ($rows as $index => $row) {
                $dueDays = (int) ($row['suggested_due_days'] ?? 30);
                $dueDays = max(15, min(90, $dueDays));

                $this->measures->createEngineSuggestion($survey, [
                    'factor_label' => $row['factor_label'],
                    'measure_description' => $row['measure_description'],
                    'suggested_due_date' => now()->addDays($dueDays)->toDateString(),
                    'suggested_due_origin' => 'talents',
                    'sort_order' => $index,
                ]);
            }

            AiAnalysis::query()->create([
                'survey_id' => $survey->id,
                'type' => Nr1AiAnalyzer::TYPE_NR1_ACTION_MEASURES,
                'content' => $result['content'],
                'prompt_tokens' => $result['prompt_tokens'],
                'completion_tokens' => $result['completion_tokens'],
                'model_used' => $result['model_used'],
                'generated_by' => $actor?->id,
            ]);

            $survey->forceFill(['action_measures_generated_at' => now()])->save();
        });

        return count($rows);
    }

    /**
     * @return list<array{factor_label: string, measure_description: string, suggested_due_days: int}>
     */
    public function parseMeasures(string $content): array
    {
        $json = trim($content);
        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $json, $matches) === 1) {
            $json = $matches[1];
        }

        $start = strpos($json, '{');
        $end = strrpos($json, '}');
        if ($start === false || $end === false || $end <= $start) {
            return [];
        }

        try {
            $decoded = json_decode(substr($json, $start, $end - $start + 1), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        $items = is_array($decoded['measures'] ?? null) ? $decoded['measures'] : [];
        $rows = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $factor = trim((string) ($item['factor_label'] ?? ''));
            $description = trim((string) ($item['measure_description'] ?? ''));
            if ($factor === '' || $description === '') {
                continue;
            }
            $rows[] = [
                'factor_label' => mb_substr($factor, 0, 255),
                'measure_description' => mb_substr($description, 0, 5000),
                'suggested_due_days' => (int) ($item['suggested_due_days'] ?? 30),
            ];
            if (count($rows) >= 6) {
                break;
            }
        }

        return $rows;
    }
}
