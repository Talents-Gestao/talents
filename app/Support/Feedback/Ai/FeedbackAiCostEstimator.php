<?php

declare(strict_types=1);

namespace App\Support\Feedback\Ai;

/**
 * Custo ESTIMADO de uma chamada, em centavos de real, a partir dos tokens informados pelo provedor. Tabela de
 * preço de referência (US$ por milhão de tokens) e câmbio fixo: serve para acompanhar e limitar o uso, não
 * para faturamento. Modelo desconhecido usa a faixa mais cara conhecida, para o limite nunca ficar frouxo.
 */
final class FeedbackAiCostEstimator
{
    public const USD_TO_BRL = 5.50;

    /** Prefixo do modelo => [entrada, saída] em US$ por milhão de tokens (o prefixo mais longo vence). */
    private const PRICES = [
        'gpt-4o-mini' => [0.15, 0.60],
        'gpt-4o' => [2.50, 10.00],
        'gpt-4.1-nano' => [0.10, 0.40],
        'gpt-4.1-mini' => [0.40, 1.60],
        'gpt-4.1' => [2.00, 8.00],
        'gpt-5-mini' => [0.25, 2.00],
        'gpt-5' => [1.25, 10.00],
        'claude-3-5-haiku' => [0.80, 4.00],
        'claude-haiku' => [1.00, 5.00],
        'claude-3-5-sonnet' => [3.00, 15.00],
        'claude-sonnet' => [3.00, 15.00],
        'claude-opus' => [5.00, 25.00],
    ];

    private const FALLBACK = [5.00, 25.00];

    public static function cents(string $provider, string $model, int $inputTokens, int $outputTokens): int
    {
        if ($provider === FeedbackAiGate::PROVIDER_DEMO) {
            return 0;
        }

        [$in, $out] = self::pricesFor($model);
        $usd = ($inputTokens * $in + $outputTokens * $out) / 1_000_000;

        return (int) ceil($usd * self::USD_TO_BRL * 100);
    }

    /** @return array{0: float, 1: float} */
    private static function pricesFor(string $model): array
    {
        $model = strtolower($model);
        $best = null;

        foreach (self::PRICES as $prefix => $prices) {
            if (str_starts_with($model, $prefix) && ($best === null || strlen($prefix) > strlen($best))) {
                $best = $prefix;
            }
        }

        return $best === null ? self::FALLBACK : self::PRICES[$best];
    }
}
