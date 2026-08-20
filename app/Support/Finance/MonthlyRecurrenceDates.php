<?php

declare(strict_types=1);

namespace App\Support\Finance;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Vencimentos mensais a partir de uma data-base, sem transbordar o fim do mês
 * (ex.: 31/01 → 28/02 → 31/03).
 */
final class MonthlyRecurrenceDates
{
    /**
     * @return list<Carbon>
     */
    public static function dueDates(CarbonInterface|string $firstDue, int $months): array
    {
        if ($months < 1) {
            throw new InvalidArgumentException('A duração deve ser de pelo menos 1 mês.');
        }

        $base = Carbon::parse($firstDue)->startOfDay();
        $dates = [];

        for ($i = 0; $i < $months; $i++) {
            $dates[] = $base->copy()->addMonthsNoOverflow($i);
        }

        return $dates;
    }
}
