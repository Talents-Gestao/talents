<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PsychosocialMeasure;
use App\Models\Survey;
use App\Models\SurveyCampaignRegistration;
use App\Models\SurveyMethodVersion;
use App\Support\SurveyPublicationGate;

/**
 * Complementos do painel V11 que não são cálculo de resultado: "Próximas ações" (do plano) e a lista de campanhas
 * da empresa para os filtros da aba Resultados. Só metadados e texto do plano; nenhuma resposta.
 */
class PsychosocialDashboardExtras
{
    private const NEXT_ACTIONS = 3;

    /**
     * As três primeiras medidas ainda abertas do plano, com o chip do HTML aprovado.
     *
     * @return list<array{id: int, factor: string, description: string, status: string, status_label: string, chip: string, confirmed: bool, due_date: ?string, responsible: ?string}>
     */
    public function nextActions(Survey $survey): array
    {
        return PsychosocialMeasure::query()
            ->where('survey_id', $survey->id)
            ->whereNotIn('status', [PsychosocialMeasure::STATUS_IMPLEMENTED])
            ->with('confirmedResponsibleUser:id,name')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(self::NEXT_ACTIONS)
            ->get()
            ->map(function (PsychosocialMeasure $measure): array {
                $confirmed = $measure->confirmed_at !== null;
                $due = $confirmed ? $measure->confirmed_due_date : $measure->suggested_due_date;

                return [
                    'id' => $measure->id,
                    'factor' => (string) $measure->factor_label,
                    'description' => (string) $measure->measure_description,
                    'status' => $measure->status,
                    'status_label' => PsychosocialMeasure::PLAN_LABELS[$measure->status] ?? $measure->status,
                    'chip' => $confirmed ? 'Responsável confirmado' : 'A confirmar',
                    'confirmed' => $confirmed,
                    'due_date' => $due?->format('d/m/Y'),
                    'responsible' => $confirmed
                        ? ($measure->confirmedResponsibleUser?->name ?? $measure->confirmed_responsible_text)
                        : $measure->responsible_text,
                ];
            })
            ->all();
    }

    /**
     * Campanhas V11 da empresa para trocar de campanha nos filtros (período e estabelecimento só estreitam a lista).
     * A empresa só vê as publicadas; a Talents vê todas.
     *
     * @param  callable(Survey): string  $url
     * @return list<array{id: int, title: string, url: string, period_label: ?string, unit_name: ?string, starts_at: ?string, current: bool}>
     */
    public function campaigns(Survey $survey, bool $onlyPublished, callable $url): array
    {
        $query = Survey::query()
            ->where('company_id', $survey->company_id)
            ->whereHas('methodVersion', fn ($q) => $q->where('code', SurveyMethodVersion::CODE_TALENTS39_V1));

        if ($onlyPublished) {
            SurveyPublicationGate::scopeVisible($query);
        }

        $units = SurveyCampaignRegistration::query()
            ->whereIn('survey_id', (clone $query)->select('surveys.id'))
            ->pluck('unit_name', 'survey_id');

        return $query->orderByDesc('starts_at')->orderByDesc('id')->get()
            ->map(fn (Survey $item): array => [
                'id' => $item->id,
                'title' => (string) $item->title,
                'url' => $url($item),
                'period_label' => match (true) {
                    $item->starts_at && $item->ends_at => $item->starts_at->format('d/m/Y').' a '.$item->ends_at->format('d/m/Y'),
                    (bool) $item->starts_at => 'A partir de '.$item->starts_at->format('d/m/Y'),
                    default => null,
                },
                'unit_name' => $units[$item->id] ?? null,
                'starts_at' => $item->starts_at?->format('Y-m'),
                'current' => $item->id === $survey->id,
            ])
            ->all();
    }
}
