<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Finance;

use App\Support\Finance\MonthlyRecurrenceDates;
use InvalidArgumentException;
use Tests\TestCase;

class MonthlyRecurrenceDatesTest extends TestCase
{
    public function test_generates_monthly_due_dates_without_overflow(): void
    {
        $dates = MonthlyRecurrenceDates::dueDates('2026-01-31', 4);

        $this->assertSame([
            '2026-01-31',
            '2026-02-28',
            '2026-03-31',
            '2026-04-30',
        ], array_map(fn ($d) => $d->toDateString(), $dates));
    }

    public function test_keeps_same_day_when_month_has_that_day(): void
    {
        $dates = MonthlyRecurrenceDates::dueDates('2026-08-10', 6);

        $this->assertSame([
            '2026-08-10',
            '2026-09-10',
            '2026-10-10',
            '2026-11-10',
            '2026-12-10',
            '2027-01-10',
        ], array_map(fn ($d) => $d->toDateString(), $dates));
    }

    public function test_rejects_zero_months(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MonthlyRecurrenceDates::dueDates('2026-08-10', 0);
    }
}
