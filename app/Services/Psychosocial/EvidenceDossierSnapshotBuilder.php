<?php

declare(strict_types=1);

namespace App\Services\Psychosocial;

use App\Exceptions\PsychosocialDocumentException;
use App\Models\Survey;
use App\Services\ExecutiveBriefSnapshotBuilder;

/**
 * Snapshot do dossiê de evidências. Mesma base do painel; a polaridade pendente impede a liberação.
 * Gravidade, probabilidade, risco do inventário e assinatura permanecem em branco.
 */
class EvidenceDossierSnapshotBuilder implements DocumentSnapshotBuilder
{
    public const SCHEMA = 'evidence_dossier.v1';

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
                'O dossiê de evidências não pode ser liberado: a polaridade do instrumento desta campanha não foi revalidada.'
            );
        }
    }

    public function build(Survey $survey): array
    {
        return array_merge($this->baseBuilder->buildForSchema($survey, self::SCHEMA), [
            'inventory' => array_map(fn () => null, PgrSubsidySnapshotBuilder::INVENTORY_FIELDS),
        ]);
    }
}
