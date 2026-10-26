<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PsychosocialMeasure;
use App\Models\Survey;
use App\Models\SurveyCampaignRegistration;
use App\Services\Psychosocial\DocumentSnapshotBuilder;

/**
 * Snapshot congelado do resumo executivo. Tudo vem do painel gerencial (números gravados e cascata
 * já aplicada), do cadastro técnico e das medidas gravadas. Lacuna continua nula; nada é inferido.
 */
class ExecutiveBriefSnapshotBuilder implements DocumentSnapshotBuilder
{
    public const SCHEMA = 'executive_brief.v1';

    public const DECISION_PENDING = 'Decisão do responsável técnico ainda não registrada.';

    public const NO_MEASURES = 'Nenhuma medida registrada';

    public const REFERENCE = 'FAQ MTE 30/04/2026, questões 2, 5, 10, 11, 12, 14 e 16; NR-1 vigente; Manual de Interpretação do GRO/PGR do MTE (2026).';

    private const REGISTRATION_FIELDS = [
        'unit_name',
        'process_name',
        'legal_name_snapshot',
        'cnpj_snapshot',
        'eligible_source',
        'exposure_groups',
        'aep_summary',
        'observation_summary',
        'existing_controls',
        'technical_responsible_name',
        'technical_responsible_role',
        'technical_responsible_registration',
        'approving_organization_name',
    ];

    public function __construct(private readonly PsychosocialDashboardBuilder $dashboardBuilder)
    {
    }

    public function schema(): string
    {
        return self::SCHEMA;
    }

    public function assertReleasable(Survey $survey): void
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Survey $survey): array
    {
        return $this->buildForSchema($survey, self::SCHEMA);
    }

    /**
     * Base comum dos documentos novos: painel (cascata aplicada), cadastro técnico e medidas.
     *
     * @return array<string, mixed>
     */
    public function buildForSchema(Survey $survey, string $schema): array
    {
        $survey->loadMissing('company');
        $dashboard = $this->dashboardBuilder->build($survey);
        $registration = SurveyCampaignRegistration::query()->where('survey_id', $survey->id)->first();

        return [
            'schema' => $schema,
            'released_at' => now()->toIso8601String(),
            'company' => ['name' => $survey->company?->name],
            'survey' => [
                'id' => $survey->id,
                'title' => $survey->title,
                'starts_at' => $survey->starts_at?->toDateString(),
                'ends_at' => $survey->ends_at?->toDateString(),
            ],
            'method' => $dashboard['method'],
            'band_disclaimer' => $dashboard['band_disclaimer'],
            'polarity_warning' => $dashboard['polarity_warning'],
            'min_group_size' => $dashboard['min_group_size'],
            'has_results' => $dashboard['has_results'],
            'respondents' => $dashboard['respondents'],
            'eligible' => $dashboard['eligible'],
            'overall' => $dashboard['overall'],
            'dimensions' => $dashboard['dimensions'],
            'sectors' => $dashboard['sectors'],
            'findings' => $this->findings($dashboard),
            'registration' => array_combine(
                self::REGISTRATION_FIELDS,
                array_map(fn (string $field) => $registration?->getAttribute($field), self::REGISTRATION_FIELDS),
            ),
            'measures' => $this->measures($survey),
            'measures_empty_text' => self::NO_MEASURES,
            'reference' => self::REFERENCE,
        ] + ($survey->usesReviewFlow() ? ['document_flow' => 'review_round'] : []);
    }

    /**
     * Uma linha para o geral e uma para cada dimensão publicada.
     *
     * @param  array<string, mixed>  $dashboard
     * @return list<array<string, mixed>>
     */
    private function findings(array $dashboard): array
    {
        $row = fn (string $cut, array $result): array => [
            'cut' => $cut,
            'score' => $result['score'],
            'risk_level' => $result['risk_level'],
            'band_label' => $result['band_label'],
            'n' => $result['n'],
            'evidence_n' => $result['n'],
            'decision_requested' => self::DECISION_PENDING,
        ];

        $rows = $dashboard['overall'] !== null ? [$row('Geral', $dashboard['overall'])] : [];
        foreach ($dashboard['dimensions'] as $dimension) {
            $rows[] = $row($dimension['title'], $dimension);
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function measures(Survey $survey): array
    {
        // Campanha V11: rótulos do plano (Proposta, Confirmada…) e responsável/prazo confirmados pela empresa.
        $planLabels = $survey->usesReviewFlow();

        return PsychosocialMeasure::query()
            ->where('survey_id', $survey->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (PsychosocialMeasure $measure) => [
                'factor_label' => $measure->factor_label,
                'measure_description' => $measure->measure_description,
                'evidence_basis' => $measure->evidence_basis,
                'status' => $measure->status,
                'status_label' => $planLabels ? $measure->planLabel() : $measure->statusLabel(),
                'responsible_text' => $measure->responsible_text,
                'suggested_due_date' => $measure->suggested_due_date?->toDateString(),
            ] + ($planLabels ? [
                'confirmed_responsible' => $measure->hasConfirmedResponsible()
                    ? collect([$measure->confirmedResponsibleUser?->name, $measure->confirmed_responsible_text])->filter()->implode(' · ')
                    : null,
                'confirmed_due_date' => $measure->confirmed_due_date?->toDateString(),
            ] : []))
            ->values()
            ->all();
    }
}
