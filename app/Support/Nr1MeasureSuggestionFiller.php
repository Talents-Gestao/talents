<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\PsychosocialMeasure;
use App\Models\Survey;
use App\Models\SurveyMethodVersion;
use App\Models\SurveyResult;

/**
 * Preenchimento automático das sugestões do motor com o catálogo V11 (Nr1MeasureCatalog), só em campanhas V11:
 * indicador, objetivo, resultado esperado, responsável sugerido (área/função), origem e justificativa do prazo.
 * Só preenche campos vazios. Modelo anterior (COPSOQ) e medidas manuais ficam sem preenchimento.
 * A faixa da dimensão vem dos resultados já gravados (survey_results, linha geral da dimensão): nada é recalculado.
 */
final class Nr1MeasureSuggestionFiller
{
    public const CATALOG_FIELDS = ['indicator', 'responsible_text', 'suggested_due_origin', 'suggested_due_note'];

    public static function appliesTo(Survey $survey): bool
    {
        return $survey->usesReviewFlow();
    }

    /**
     * Faixa de cada dimensão da campanha (linha geral, sem setor): chave da dimensão => rótulo da faixa V11.
     *
     * @return array<string, string>
     */
    public static function bands(Survey $survey): array
    {
        return SurveyResult::query()
            ->with('section')
            ->where('survey_id', $survey->id)
            ->whereNull('department_id')
            ->whereNotNull('survey_template_section_id')
            ->get()
            ->mapWithKeys(fn (SurveyResult $result) => [
                Nr1MeasureCatalog::dimensionKey((string) ($result->section?->title ?? $result->meta['section_title'] ?? '')) => Nr1BandLabels::label($result->risk_level, SurveyMethodVersion::CODE_TALENTS39_V1),
            ])
            ->all();
    }

    /**
     * Campos do catálogo para a dimensão (vazio se o rótulo não é uma dimensão V11). $days null: sem justificativa do
     * prazo (prazo não gravado).
     *
     * @param  array<string, string>  $bands
     * @return array<string, string>
     */
    public static function catalogFields(string $factorLabel, array $bands, ?int $days): array
    {
        $entry = Nr1MeasureCatalog::entry($factorLabel);
        if ($entry === null) {
            return [];
        }

        $fields = [
            'indicator' => $entry['indicator'],
            'responsible_text' => $entry['responsible'],
            'suggested_due_origin' => 'talents',
        ];
        if ($days !== null) {
            $fields['suggested_due_note'] = Nr1MeasureCatalog::dueNote($bands[Nr1MeasureCatalog::dimensionKey($factorLabel)] ?? null, $days);
        }

        return $fields;
    }

    /**
     * Só os campos que estão vazios na medida (nunca sobrescreve).
     *
     * @param  array<string, string>  $fields
     * @return array<string, string>
     */
    public static function onlyEmpty(PsychosocialMeasure $measure, array $fields): array
    {
        return array_filter($fields, fn ($value, string $field) => blank($measure->getAttribute($field)), ARRAY_FILTER_USE_BOTH);
    }

    /** Dias entre a criação e o prazo sugerido gravado (null sem prazo). */
    public static function daysToDue(PsychosocialMeasure $measure): ?int
    {
        if ($measure->suggested_due_date === null || $measure->created_at === null) {
            return null;
        }

        return (int) $measure->created_at->copy()->startOfDay()->diffInDays($measure->suggested_due_date->copy()->startOfDay());
    }
}
