<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiAnalysis;
use App\Models\AiSetting;
use App\Models\PsychosocialMeasure;
use App\Models\Survey;
use App\Models\User;
use App\Support\Nr1GroupPrivacy;
use App\Support\Nr1MeasureCatalog;
use App\Support\Nr1MeasureSuggestionFiller;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Gera medidas do plano a partir dos resultados agregados (uma chamada de IA), de forma idempotente por dimensão:
 * chave lógica = campanha + dimensão normalizada (Nr1MeasureCatalog::dimensionKey) + origem engine_suggestion.
 *
 * Ao (re)gerar, para cada dimensão devolvida pela IA (duas medidas na mesma dimensão viram uma, com as ações juntadas):
 * - pula a dimensão se já houver medida dela fora de «A aprovar» (confirmada, em andamento, replanejada, concluída),
 *   medida manual da Talents ou sugestão do motor editada pela Talents (critério: algum evento com autor além de
 *   «created»; atualizações automáticas gravam eventos sem autor e não contam como edição);
 * - atualiza no lugar a sugestão intocada (com evento sem autor) e só cria quando a dimensão não tem nenhuma.
 * Sugestões intocadas de dimensões que a IA não devolveu mais saem, como antes. Em seguida, a regra única de
 * PsychosocialMeasureDeduplicator junta na hora as duplicatas da campanha (qualquer origem): só sai sugestão do
 * motor intocada. Uma trava na linha da campanha serializa execuções simultâneas.
 */
final class ActionMeasuresGenerator
{
    public const MAX_MEASURES = 6;

    public function __construct(
        private readonly Nr1AiAnalyzer $analyzer,
        private readonly PsychosocialMeasureService $measures,
        private readonly PsychosocialMeasureDeduplicator $deduplicator,
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

        $rows = $this->onePerDimension($this->parseMeasures($result['content']));
        if ($rows === []) {
            throw new RuntimeException('A IA não devolveu medidas utilizáveis.');
        }

        return DB::transaction(function () use ($survey, $actor, $rows, $result): int {
            Survey::query()->whereKey($survey->id)->lockForUpdate()->first();

            $existing = PsychosocialMeasure::query()->where('survey_id', $survey->id)->orderBy('id')->get()
                ->groupBy(fn (PsychosocialMeasure $m) => Nr1MeasureCatalog::dimensionKey((string) $m->factor_label));
            // Catálogo V11 (só campanhas V11): indicador, objetivo, resultado esperado, responsável e prazo.
            $v11 = Nr1MeasureSuggestionFiller::appliesTo($survey);
            $bands = $v11 ? Nr1MeasureSuggestionFiller::bands($survey) : [];
            $touched = 0;
            $keep = [];

            foreach ($rows as $index => $row) {
                $same = $existing->get(Nr1MeasureCatalog::dimensionKey($row['factor_label']), collect());
                array_push($keep, ...$same->pluck('id')->all());

                $blocked = $same->contains(fn (PsychosocialMeasure $m) => $m->status !== PsychosocialMeasure::STATUS_TO_APPROVE
                    || $m->origin !== PsychosocialMeasure::ORIGIN_ENGINE_SUGGESTION
                    || $this->measures->isEditedByTalents($m));
                if ($blocked) {
                    continue;
                }

                $dueDays = max(Nr1MeasureCatalog::DUE_MIN_DAYS, min(Nr1MeasureCatalog::DUE_MAX_DAYS, (int) $row['suggested_due_days']));
                $data = [
                    'factor_label' => $row['factor_label'],
                    'measure_description' => $row['measure_description'],
                    'suggested_due_date' => now()->addDays($dueDays)->toDateString(),
                    'suggested_due_origin' => 'talents',
                    'sort_order' => $index,
                ];

                $catalog = $v11 ? Nr1MeasureSuggestionFiller::catalogFields($row['factor_label'], $bands, $dueDays) : [];

                $current = $same->first();
                if ($current !== null) {
                    // Intocada: o prazo e a justificativa automática dele acompanham; os demais só se estiverem vazios.
                    $fill = Nr1MeasureSuggestionFiller::onlyEmpty($current, array_diff_key($catalog, ['suggested_due_note' => true]));
                    if (isset($catalog['suggested_due_note'])) {
                        $fill['suggested_due_note'] = $catalog['suggested_due_note'];
                    }
                    $this->measures->refreshEngineSuggestion($current, $data + $fill);
                } else {
                    $keep[] = $this->measures->createEngineSuggestion($survey, $data + $catalog)->id;
                }
                $touched++;
            }

            // Sugestões intocadas de dimensões que a IA não devolveu mais (substituição, como antes).
            $existing->flatten()
                ->reject(fn (PsychosocialMeasure $m) => in_array($m->id, $keep, true))
                ->filter(fn (PsychosocialMeasure $m) => $m->origin === PsychosocialMeasure::ORIGIN_ENGINE_SUGGESTION
                    && $m->status === PsychosocialMeasure::STATUS_TO_APPROVE
                    && ! $this->measures->isEditedByTalents($m))
                ->each(fn (PsychosocialMeasure $m) => $m->delete());

            // Uma medida por dimensão: junta na hora as duplicatas (inclusive antigas e de outra origem).
            $this->deduplicator->applyForSurvey($survey);

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

            return $touched;
        });
    }

    /**
     * Uma medida por dimensão, sem depender da IA: a segunda medida da mesma dimensão é juntada ao texto da primeira
     * (sem repetir frase); mantém o prazo da primeira e o limite de 6 dimensões.
     *
     * @param  list<array{factor_label: string, measure_description: string, suggested_due_days: int}>  $rows
     * @return list<array{factor_label: string, measure_description: string, suggested_due_days: int}>
     */
    public function onePerDimension(array $rows): array
    {
        $byKey = [];
        foreach ($rows as $row) {
            $key = Nr1MeasureCatalog::dimensionKey($row['factor_label']);
            if (isset($byKey[$key])) {
                $byKey[$key]['measure_description'] = Nr1MeasureCatalog::mergeDescriptions($byKey[$key]['measure_description'], $row['measure_description']);

                continue;
            }
            if (count($byKey) >= self::MAX_MEASURES) {
                continue;
            }
            $row['factor_label'] = Nr1MeasureCatalog::v11Dimension($row['factor_label']) ?? $row['factor_label'];
            $byKey[$key] = $row;
        }

        return array_values($byKey);
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
                'suggested_due_days' => (int) ($item['suggested_due_days'] ?? Nr1MeasureCatalog::DUE_DEFAULT_DAYS),
            ];
            // Lê um pouco além do limite para que uma segunda medida da mesma dimensão seja juntada, não perdida.
            if (count($rows) >= 2 * self::MAX_MEASURES) {
                break;
            }
        }

        return $rows;
    }
}
