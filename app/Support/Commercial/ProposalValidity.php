<?php

declare(strict_types=1);

namespace App\Support\Commercial;

use Carbon\CarbonInterface;

/**
 * Validade da proposta comercial: created_at + dias configurados no PDF.
 */
final class ProposalValidity
{
    /** Dias à frente (além das já expiradas) para o aviso «prestes a vencer». */
    public const WARNING_DAYS = 3;

    /** Máximo de cards no modal. */
    public const MODAL_LIMIT = 50;

    public static function daysFromSettings(?int $pdfValidadeDias): int
    {
        return max(1, (int) ($pdfValidadeDias ?? 7));
    }

    public static function validUntil(CarbonInterface $createdAt, int $validityDays): CarbonInterface
    {
        return $createdAt->copy()->startOfDay()->addDays($validityDays);
    }

    /**
     * Propostas criadas neste dia ou antes entram no aviso
     * (validade já passou ou resta no máximo WARNING_DAYS).
     */
    public static function createdAtCutoff(
        CarbonInterface $today,
        int $validityDays,
        int $warningDays = self::WARNING_DAYS,
    ): CarbonInterface {
        return $today->copy()->startOfDay()->addDays($warningDays)->subDays($validityDays);
    }

    public static function isExpired(CarbonInterface $validUntil, CarbonInterface $today): bool
    {
        return $validUntil->lt($today->copy()->startOfDay());
    }
}
