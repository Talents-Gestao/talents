<?php

declare(strict_types=1);

namespace App\Support\Commercial;

use App\Models\User;

/**
 * Comissão comercial: percentual fixo por vendedor (Equipe).
 *
 * Com/sem comissão e a % são definidos no cadastro do administrador.
 * Proposta/venda guardam snapshot (commission_percent / commission_cents).
 * O registro em commercial_commissions só é criado quando amount_cents > 0.
 */
final class OptionalCommission
{
    /**
     * Percentual fixo cadastrado no usuário da Equipe.
     */
    public static function percentForSeller(?User $seller): float
    {
        if ($seller === null) {
            return 0.0;
        }

        return self::clampPercent((float) ($seller->commission_percent ?? 0));
    }

    /**
     * Snapshot para gravar na proposta/venda a partir do vendedor.
     */
    public static function resolveForProposal(?User $seller): float
    {
        return self::percentForSeller($seller);
    }

    public static function centsFromPercent(int $baseCents, float $percent): int
    {
        if ($baseCents < 1 || $percent <= 0) {
            return 0;
        }

        return (int) round($baseCents * $percent / 100);
    }

    /**
     * Conversão em venda: usa o snapshot da proposta (já derivado do vendedor).
     *
     * @return array{percent: float, cents: int}
     */
    public static function forConversion(
        float $storedPercent,
        int $storedCents,
        int $baseCents,
        bool $recomputeFromPercent,
    ): array {
        $percent = self::clampPercent($storedPercent);
        $cents = $recomputeFromPercent
            ? self::centsFromPercent($baseCents, $percent)
            : max(0, $storedCents);

        if ($cents < 1 && $percent > 0) {
            $cents = self::centsFromPercent($baseCents, $percent);
        }

        return [
            'percent' => $percent,
            'cents' => $cents,
        ];
    }

    private static function clampPercent(float $percent): float
    {
        return max(0.0, min(100.0, $percent));
    }
}
