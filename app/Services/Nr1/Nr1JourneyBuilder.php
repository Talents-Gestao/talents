<?php

declare(strict_types=1);

namespace App\Services\Nr1;

use App\Models\Nr1Training;
use App\Models\Survey;
use App\Models\SurveyAudienceLink;
use App\Support\Nr1Orientation;

/**
 * Conteúdo de «Minha jornada» (Caderno §7) para o colaborador: a orientação (sempre) e SÓ os treinamentos contratados E
 * liberados ao público do link (T05, T06). Nunca resultados, ofertas, preços nem dado de outros colaboradores.
 */
class Nr1JourneyBuilder
{
    /**
     * @param  callable(Nr1Training): string  $materialUrl
     * @return array{orientation: array<string, mixed>, audience_label: ?string, trainings: list<array<string, mixed>>}|null
     */
    public function build(Survey $survey, ?SurveyAudienceLink $link, callable $materialUrl): ?array
    {
        if (! $survey->usesReviewFlow()) {
            return null;
        }

        // Sem link por público não há como saber quem é o colaborador: nenhum treinamento é mostrado.
        $trainings = $link === null
            ? collect()
            : Nr1Training::query()
                ->where('survey_id', $survey->id)
                ->where('audience', $link->audience)
                ->whereNotNull('contracted_at')
                ->whereNotNull('released_at')
                ->orderBy('modality')
                ->get();

        return [
            'orientation' => Nr1Orientation::content(),
            'audience_label' => $link?->label(),
            'trainings' => $trainings->map(fn (Nr1Training $training): array => [
                'id' => $training->id,
                'modality' => $training->modality,
                'label' => Nr1Training::MODALITY_LABELS[$training->modality] ?? $training->modality,
                'scope' => $training->scope,
                'period' => $training->starts_on || $training->ends_on
                    ? ($training->starts_on?->format('d/m/Y') ?? '—').' a '.($training->ends_on?->format('d/m/Y') ?? '—')
                    : null,
                'link_url' => $training->link_url,
                'material_name' => $training->material_name,
                'material_url' => $training->material_path ? $materialUrl($training) : null,
                'location' => $training->location,
                'event_at' => $training->event_at?->format('d/m/Y H:i'),
                'facilitator' => $training->facilitator,
            ])->values()->all(),
        ];
    }
}
