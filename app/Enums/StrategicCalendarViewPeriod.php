<?php

namespace App\Enums;

use Carbon\Carbon;

enum StrategicCalendarViewPeriod: string
{
    case TwoMonths = 'two_months';
    case ThreeMonths = 'three_months';
    case Quarter = 'quarter';
    case Annual = 'annual';

    public function label(): string
    {
        return match ($this) {
            self::TwoMonths => '2 meses',
            self::ThreeMonths => '3 meses',
            self::Quarter => 'Trimestre atual',
            self::Annual => 'Ano civil atual',
        };
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    public function range(Carbon $now): array
    {
        $now = $now->copy()->timezone(config('app.timezone', 'America/Sao_Paulo'));

        return match ($this) {
            self::TwoMonths => [
                'start' => $now->copy()->startOfMonth()->startOfDay(),
                'end' => $now->copy()->addMonthNoOverflow()->endOfMonth()->endOfDay(),
            ],
            self::ThreeMonths => [
                'start' => $now->copy()->startOfMonth()->startOfDay(),
                'end' => $now->copy()->addMonthsNoOverflow(2)->endOfMonth()->endOfDay(),
            ],
            self::Quarter => [
                'start' => $now->copy()->startOfQuarter()->startOfDay(),
                'end' => $now->copy()->endOfQuarter()->endOfDay(),
            ],
            self::Annual => [
                'start' => $now->copy()->startOfYear()->startOfDay(),
                'end' => $now->copy()->endOfYear()->endOfDay(),
            ],
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
