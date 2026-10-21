<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\SurveyMethodVersion;
use InvalidArgumentException;
use LogicException;

/**
 * Índice talents39_v1: soma dos escores orientados ÷ respostas válidas, arredondado
 * meio para cima em centésimos sobre a fração exata; a faixa é decidida depois do arredondamento.
 */
final class Nr1PooledItemScore
{
    public function __construct(
        public readonly int $numerator,
        public readonly int $denominator,
        public readonly int $hundredths,
        public readonly string $riskLevel,
    ) {
    }

    public static function fromSums(int $numerator, int $denominator, SurveyMethodVersion $method): self
    {
        self::assertSupported($method);

        $hundredths = self::roundHalfUpToHundredths($numerator, $denominator);

        return new self(
            $numerator,
            $denominator,
            $hundredths,
            self::bandForHundredths($hundredths, (float) $method->green_max, (float) $method->yellow_max),
        );
    }

    /**
     * Inteiros puros: nenhum float intermediário é cortado antes do arredondamento.
     */
    public static function roundHalfUpToHundredths(int $numerator, int $denominator): int
    {
        if ($denominator <= 0 || $numerator < 0) {
            throw new InvalidArgumentException('Fração inválida para o índice talents39_v1.');
        }

        return intdiv(200 * $numerator + $denominator, 2 * $denominator);
    }

    public static function bandForHundredths(int $hundredths, float $greenMax, float $yellowMax): string
    {
        if ($hundredths <= (int) round($greenMax * 100)) {
            return 'green';
        }

        if ($hundredths <= (int) round($yellowMax * 100)) {
            return 'yellow';
        }

        return 'red';
    }

    /**
     * Valor para a coluna decimal de 3 casas: 260 centésimos viram «2.600».
     */
    public function storedScore(): string
    {
        return intdiv($this->hundredths, 100).'.'.str_pad((string) ($this->hundredths % 100), 2, '0', STR_PAD_LEFT).'0';
    }

    private static function assertSupported(SurveyMethodVersion $method): void
    {
        $expected = [
            'aggregation' => 'pooled_item_mean',
            'weighting' => 'equal',
            'rounding_mode' => 'half_up',
            'rounding_scale' => 2,
            'band_basis' => 'rounded',
        ];

        foreach ($expected as $attribute => $value) {
            if ($method->getAttribute($attribute) !== $value) {
                throw new LogicException("Método «{$method->code}» não é suportado pelo índice de respostas válidas ({$attribute}).");
            }
        }
    }
}
