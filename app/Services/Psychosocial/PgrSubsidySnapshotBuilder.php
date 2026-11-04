<?php

declare(strict_types=1);

namespace App\Services\Psychosocial;

use App\Exceptions\PsychosocialDocumentException;
use App\Models\Survey;
use App\Services\ExecutiveBriefSnapshotBuilder;
use App\Support\Nr1BandLabels;

/**
 * Snapshot do subsídio AEP/PGR. Parte da mesma base do resumo executivo e acrescenta
 * leitura orientada ao inventário (prioridades e decisão solicitada por faixa).
 * Gravidade, probabilidade, nível de risco do inventário e assinatura continuam nulos —
 * só o responsável técnico preenche (PRD 8.11.1).
 */
class PgrSubsidySnapshotBuilder implements DocumentSnapshotBuilder
{
    public const SCHEMA = 'pgr_subsidy.v1';

    public const INVENTORY_FIELDS = [
        'severity' => 'Gravidade',
        'probability' => 'Probabilidade',
        'risk_level' => 'Nível de risco do inventário',
        'signature' => 'Assinatura do responsável técnico',
    ];

    /** Orientação ao RT a partir da faixa do questionário — não é classificação ocupacional. */
    private const DECISION_BY_BAND = [
        'green' => 'Manter no monitoramento do PGR; sem prioridade de inventário com base apenas neste indicador.',
        'yellow' => 'Avaliar inclusão deste fator no inventário de riscos psicossociais e definir controles preventivos.',
        'red' => 'Priorizar análise do responsável técnico para inventário do PGR e medidas de controle.',
    ];

    public function __construct(private readonly ExecutiveBriefSnapshotBuilder $baseBuilder)
    {
    }

    public function schema(): string
    {
        return self::SCHEMA;
    }

    public function assertReleasable(Survey $survey): void
    {
        $survey->loadMissing('instrumentVersion');

        if ($survey->instrumentVersion?->isPolarityAuditPending()) {
            throw new PsychosocialDocumentException(
                'O subsídio AEP/PGR não pode ser liberado: a polaridade do instrumento desta campanha não foi revalidada.'
            );
        }
    }

    public function build(Survey $survey): array
    {
        $base = $this->baseBuilder->buildForSchema($survey, self::SCHEMA);
        $overallLevel = is_array($base['overall'] ?? null) ? ($base['overall']['risk_level'] ?? null) : null;
        $scenario = $this->scenarioPayload($overallLevel);

        $dimensionFindings = $this->dimensionFindings($base['findings'] ?? []);
        $priorities = array_values(array_filter(
            $dimensionFindings,
            fn (array $row) => in_array($row['risk_level'], ['yellow', 'red'], true),
        ));

        $sectorPriorities = collect($base['sectors'] ?? [])
            ->filter(fn (array $sector) => in_array($sector['risk_level'] ?? null, ['yellow', 'red'], true))
            ->sortByDesc(fn (array $sector) => (float) ($sector['score'] ?? 0))
            ->values()
            ->map(fn (array $sector) => [
                'department_name' => $sector['department_name'],
                'score' => $sector['score'],
                'band_label' => $sector['band_label'],
                'risk_level' => $sector['risk_level'],
                'n' => $sector['n'],
                'decision_requested' => self::DECISION_BY_BAND[$sector['risk_level']] ?? ExecutiveBriefSnapshotBuilder::DECISION_PENDING,
            ])
            ->all();

        return array_merge($base, [
            'inventory' => array_map(fn () => null, self::INVENTORY_FIELDS),
            'findings' => $dimensionFindings,
            'pgr' => [
                'purpose' => 'Subsídio para a avaliação ergonômica preliminar (AEP) e para o inventário de riscos psicossociais do PGR. Os índices abaixo são faixa do questionário; não classificam risco ocupacional. Gravidade, probabilidade e nível de risco do inventário ficam a cargo do responsável técnico.',
                'scenario' => $scenario,
                'priorities' => $priorities,
                'sector_priorities' => $sectorPriorities,
                'inventory_guidance' => 'Preencha gravidade, probabilidade e nível de risco do inventário com base na AEP, na observação do trabalho e neste subsídio — não copie automaticamente a faixa do questionário.',
            ],
        ]);
    }

    /**
     * Dimensões (sem a linha «Geral»), piores primeiro, com evidência e decisão úteis ao RT.
     *
     * @param  list<array<string, mixed>>  $findings
     * @return list<array<string, mixed>>
     */
    private function dimensionFindings(array $findings): array
    {
        $rows = collect($findings)
            ->filter(fn (array $finding) => ($finding['cut'] ?? '') !== 'Geral')
            ->map(function (array $finding): array {
                $level = $finding['risk_level'] ?? null;
                $n = (int) ($finding['evidence_n'] ?? $finding['n'] ?? 0);

                return [
                    'cut' => $finding['cut'],
                    'score' => $finding['score'],
                    'risk_level' => $level,
                    'band_label' => $finding['band_label'],
                    'n' => $finding['n'],
                    'evidence_n' => $finding['evidence_n'] ?? $finding['n'],
                    'evidence_label' => $n > 0
                        ? "Índice agregado da dimensão na pesquisa (n = {$n})"
                        : 'Índice agregado da dimensão na pesquisa',
                    'decision_requested' => self::DECISION_BY_BAND[$level] ?? ExecutiveBriefSnapshotBuilder::DECISION_PENDING,
                ];
            })
            ->sortByDesc(fn (array $finding) => (float) ($finding['score'] ?? 0))
            ->values()
            ->all();

        return $rows;
    }

    /**
     * @return array{code: string, label: string, body: string, conduct: string}|null
     */
    private function scenarioPayload(?string $riskLevel): ?array
    {
        if (! in_array($riskLevel, ['green', 'yellow', 'red'], true)) {
            return null;
        }

        $scenarios = config('nr1_reports.scenarios', []);
        $scenario = $scenarios[$riskLevel] ?? null;
        if (! is_array($scenario)) {
            return null;
        }

        $referral = is_array($scenario['technical_referral'] ?? null) ? $scenario['technical_referral'] : [];

        return [
            'code' => $riskLevel,
            'label' => (string) ($scenario['short_label'] ?? Nr1BandLabels::label($riskLevel)),
            'body' => (string) ($referral['body'] ?? ''),
            'conduct' => (string) ($referral['conduct'] ?? ''),
        ];
    }
}
