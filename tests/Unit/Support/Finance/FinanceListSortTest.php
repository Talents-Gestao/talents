<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Finance;

use App\Support\Finance\FinanceListSort;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class FinanceListSortTest extends TestCase
{
    public function test_from_request_defaults_to_due_date(): void
    {
        $this->assertSame(FinanceListSort::DUE_DATE, FinanceListSort::fromRequest(Request::create('/')));
    }

    public function test_from_request_accepts_paid_at_and_rejects_unknown(): void
    {
        $paid = Request::create('/', 'GET', ['sort' => FinanceListSort::PAID_AT]);
        $this->assertSame(FinanceListSort::PAID_AT, FinanceListSort::fromRequest($paid));

        $unknown = Request::create('/', 'GET', ['sort' => 'created_at']);
        $this->assertSame(FinanceListSort::DUE_DATE, FinanceListSort::fromRequest($unknown));
    }

    public function test_sort_rows_by_paid_at_puts_nulls_last(): void
    {
        $rows = new Collection([
            ['id' => 1, 'paid_at' => null, 'due_date' => '2026-08-20'],
            ['id' => 2, 'paid_at' => '2026-08-10', 'due_date' => '2026-08-01'],
            ['id' => 3, 'paid_at' => '2026-08-18', 'due_date' => '2026-08-05'],
        ]);

        $sorted = FinanceListSort::sortRows($rows, FinanceListSort::PAID_AT)->pluck('id')->all();

        $this->assertSame([3, 2, 1], $sorted);
    }

    public function test_sort_rows_by_paid_at_breaks_null_ties_by_due_date(): void
    {
        $rows = new Collection([
            ['id' => 1, 'paid_at' => null, 'due_date' => '2026-08-10'],
            ['id' => 2, 'paid_at' => null, 'due_date' => '2026-08-20'],
            ['id' => 3, 'paid_at' => '2026-08-15', 'due_date' => '2026-08-01'],
        ]);

        $sorted = FinanceListSort::sortRows($rows, FinanceListSort::PAID_AT)->pluck('id')->all();

        $this->assertSame([3, 2, 1], $sorted);
    }

    public function test_sort_rows_by_due_date_keeps_newest_first(): void
    {
        $rows = new Collection([
            ['id' => 1, 'due_date' => '2026-08-01', 'paid_at' => '2026-08-20'],
            ['id' => 2, 'due_date' => '2026-08-15', 'paid_at' => null],
        ]);

        $sorted = FinanceListSort::sortRows($rows, FinanceListSort::DUE_DATE)->pluck('id')->all();

        $this->assertSame([2, 1], $sorted);
    }
}
