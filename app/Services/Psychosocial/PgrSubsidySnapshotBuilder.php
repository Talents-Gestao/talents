<?php

declare(strict_types=1);

namespace App\Services\Psychosocial;

use App\Exceptions\PsychosocialDocumentException;
use App\Models\Survey;
use App\Services\ExecutiveBriefSnapshotBuilder;

/**
 * Snapshot do subsídio AEP/PGR. Mesma base do resumo executivo; os campos do inventário
 * (gravidade, probabilidade, nível de risco e assinatura) ficam para o responsável técnico.
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
        return array_merge($this->baseBuilder->buildForSchema($survey, self::SCHEMA), [
            'inventory' => array_map(fn () => null, self::INVENTORY_FIELDS),
        ]);
    }
}
