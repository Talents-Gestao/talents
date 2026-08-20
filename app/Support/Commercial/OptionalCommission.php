<?php

declare(strict_types=1);

namespace App\Support\Commercial;

use App\Models\User;

/**
 * Comissão opcional no fluxo proposta → venda.
 *
 * Alinhado ao modelo atual de CommercialSale / CommercialCommission:
 * - proposta e venda guardam commission_percent / commission_cents (0 = sem comissão);
 * - o registo em commercial_commissions (valor a pagar) só é criado quando amount_cents > 0.
 * Propostas antigas sem o campo pay_commission continuam a usar o percentual já gravado.
 */
final class OptionalCommission
{
    public static function userIsOwner(?User $user): bool
    {
        return $user !== null && (bool) $user->is_owner;
    }

    /**
     * Owner Talents a fechar o fluxo sozinho (sem vendedor elegível) inicia sem comissão.
     */
    public static function defaultsOff(?User $seller, ?User $actor): bool
    {
        if (self::userIsOwner($seller)) {
            return true;
        }

        return $seller === null && self::userIsOwner($actor);
    }

    public static function centsFromPercent(int $baseCents, float $percent): int
    {
        if ($baseCents < 1 || $percent <= 0) {
            return 0;
        }

        return (int) round($baseCents * $percent / 100);
    }

    public static function resolvePercent(bool $payCommission, mixed $requestedPercent, float $settingsDefault): float
    {
        if (! $payCommission) {
            return 0.0;
        }

        if ($requestedPercent === null || $requestedPercent === '') {
            return self::clampPercent($settingsDefault);
        }

        return self::clampPercent((float) $requestedPercent);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function resolveFromRequest(
        array $input,
        float $settingsDefault,
        ?User $seller,
        ?User $actor,
    ): float {
        if (array_key_exists('pay_commission', $input)) {
            return self::resolvePercent(
                self::toBool($input['pay_commission']),
                $input['commission_percent'] ?? null,
                $settingsDefault,
            );
        }

        if (array_key_exists('commission_percent', $input) && $input['commission_percent'] !== null && $input['commission_percent'] !== '') {
            return self::clampPercent((float) $input['commission_percent']);
        }

        if (self::defaultsOff($seller, $actor)) {
            return 0.0;
        }

        return self::clampPercent($settingsDefault);
    }

    /**
     * Conversão em venda: honra a proposta, salvo override explícito no pedido.
     *
     * @param  array<string, mixed>  $data
     * @return array{percent: float, cents: int}
     */
    public static function forConversion(
        array $data,
        float $storedPercent,
        int $storedCents,
        int $baseCents,
        bool $recomputeFromPercent,
    ): array {
        if (array_key_exists('pay_commission', $data)) {
            $percent = self::resolvePercent(
                self::toBool($data['pay_commission']),
                $data['commission_percent'] ?? $storedPercent,
                $storedPercent,
            );

            return [
                'percent' => $percent,
                'cents' => self::centsFromPercent($baseCents, $percent),
            ];
        }

        $percent = self::clampPercent($storedPercent);
        $cents = $recomputeFromPercent
            ? self::centsFromPercent($baseCents, $percent)
            : max(0, $storedCents);

        return [
            'percent' => $percent,
            'cents' => $cents,
        ];
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $parsed ?? false;
    }

    private static function clampPercent(float $percent): float
    {
        return max(0.0, min(100.0, $percent));
    }
}
