<?php

namespace App\Support;

use App\Enums\StrategicCalendarViewPeriod;
use App\Models\Company;
use Carbon\Carbon;

class StrategicCalendarPeriod
{
    /**
     * Janela visível para a empresa conforme o plano da assinatura ativa.
     *
     * @return array{start: Carbon, end: Carbon, label: string, period: StrategicCalendarViewPeriod}|null
     */
    public static function forCompany(Company $company, ?Carbon $now = null): ?array
    {
        $subscription = $company->activeSubscription();
        $subscription?->loadMissing('plan');

        $period = $subscription?->plan?->strategic_calendar_view_period;
        if (! $period instanceof StrategicCalendarViewPeriod) {
            return null;
        }

        $now = ($now ?? Carbon::now())->timezone(config('app.timezone', 'America/Sao_Paulo'));
        $range = $period->range($now);

        return [
            'start' => $range['start'],
            'end' => $range['end'],
            'label' => $period->label(),
            'period' => $period,
        ];
    }

    /**
     * @return array{year: int, month: int}
     */
    public static function clampMonth(int $year, int $month, Carbon $start, Carbon $end, ?Carbon $now = null): array
    {
        $now = ($now ?? Carbon::now())->timezone(config('app.timezone', 'America/Sao_Paulo'));
        $requested = Carbon::create($year, $month, 1)->startOfDay();
        $rangeStartMonth = $start->copy()->startOfMonth();
        $rangeEndMonth = $end->copy()->startOfMonth();

        if ($requested->between($rangeStartMonth, $rangeEndMonth)) {
            return ['year' => $year, 'month' => $month];
        }

        if ($now->between($start, $end)) {
            return ['year' => (int) $now->year, 'month' => (int) $now->month];
        }

        if ($requested->lt($rangeStartMonth)) {
            return ['year' => (int) $rangeStartMonth->year, 'month' => (int) $rangeStartMonth->month];
        }

        return ['year' => (int) $rangeEndMonth->year, 'month' => (int) $rangeEndMonth->month];
    }

    public static function canNavigate(int $year, int $month, Carbon $start, Carbon $end, int $delta): bool
    {
        $m = $month + $delta;
        $y = $year;
        if ($m < 1) {
            $m = 12;
            $y--;
        } elseif ($m > 12) {
            $m = 1;
            $y++;
        }

        $targetStart = Carbon::create($y, $m, 1)->startOfDay();
        $targetEnd = $targetStart->copy()->endOfMonth()->endOfDay();

        return $targetStart->gte($start->copy()->startOfMonth())
            && $targetEnd->lte($end->copy()->endOfMonth()->endOfDay());
    }

    /**
     * @return array{canNavigatePrev: bool, canNavigateNext: bool}
     */
    public static function navigationFlags(int $year, int $month, Carbon $start, Carbon $end): array
    {
        return [
            'canNavigatePrev' => self::canNavigate($year, $month, $start, $end, -1),
            'canNavigateNext' => self::canNavigate($year, $month, $start, $end, 1),
        ];
    }

    /**
     * @param  array{start: Carbon, end: Carbon, label: string, period: StrategicCalendarViewPeriod}|null  $range
     * @return array{visiblePeriod: ?array, canNavigatePrev: bool, canNavigateNext: bool, year: int, month: int}
     */
    public static function resolveClientView(
        Company $company,
        int $year,
        int $month,
        ?Carbon $now = null,
    ): array {
        $now = ($now ?? Carbon::now())->timezone(config('app.timezone', 'America/Sao_Paulo'));
        $range = self::forCompany($company, $now);

        if ($range === null) {
            $year = max(2000, min(2100, $year));
            $month = max(1, min(12, $month));

            return [
                'visiblePeriod' => null,
                'canNavigatePrev' => true,
                'canNavigateNext' => true,
                'year' => $year,
                'month' => $month,
                'range' => null,
            ];
        }

        $clamped = self::clampMonth($year, $month, $range['start'], $range['end'], $now);
        $year = $clamped['year'];
        $month = $clamped['month'];
        $nav = self::navigationFlags($year, $month, $range['start'], $range['end']);

        return [
            'visiblePeriod' => [
                'start' => $range['start']->toDateString(),
                'end' => $range['end']->toDateString(),
                'label' => $range['label'],
            ],
            'canNavigatePrev' => $nav['canNavigatePrev'],
            'canNavigateNext' => $nav['canNavigateNext'],
            'year' => $year,
            'month' => $month,
            'range' => $range,
        ];
    }
}
