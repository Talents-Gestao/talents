<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Finance;

use App\Support\Finance\FinanceListSort;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * A ordenação em si roda em SQL e é coberta nos testes de feature das listas
 * (BankAccountsAndReceivablesModuleTest / FinanceReceivableLedgerOrderTest).
 */
class FinanceListSortTest extends TestCase
{
    public function test_from_request_defaults_to_due_date(): void
    {
        $this->assertSame(FinanceListSort::DUE_DATE, FinanceListSort::fromRequest(Request::create('/')));
    }

    public function test_default_sort_follows_the_status_tab(): void
    {
        $this->assertSame(FinanceListSort::DUE_DATE, FinanceListSort::defaultForStatus('pending'));
        $this->assertSame(FinanceListSort::PAID_AT, FinanceListSort::defaultForStatus('paid'));
        $this->assertSame(FinanceListSort::DUE_DATE, FinanceListSort::defaultForStatus(''));
        $this->assertSame(FinanceListSort::DUE_DATE, FinanceListSort::defaultForStatus(null));

        $paidTab = Request::create('/', 'GET', ['status' => 'paid']);
        $this->assertSame(FinanceListSort::PAID_AT, FinanceListSort::fromRequest($paidTab));
    }

    public function test_from_request_accepts_explicit_sort_and_rejects_unknown(): void
    {
        $paid = Request::create('/', 'GET', ['sort' => FinanceListSort::PAID_AT]);
        $this->assertSame(FinanceListSort::PAID_AT, FinanceListSort::fromRequest($paid));

        $dueOnPaidTab = Request::create('/', 'GET', ['status' => 'paid', 'sort' => FinanceListSort::DUE_DATE]);
        $this->assertSame(FinanceListSort::DUE_DATE, FinanceListSort::fromRequest($dueOnPaidTab));

        $unknown = Request::create('/', 'GET', ['sort' => 'created_at']);
        $this->assertSame(FinanceListSort::DUE_DATE, FinanceListSort::fromRequest($unknown));

        $unknownOnPaidTab = Request::create('/', 'GET', ['status' => 'paid', 'sort' => 'created_at']);
        $this->assertSame(FinanceListSort::PAID_AT, FinanceListSort::fromRequest($unknownOnPaidTab));
    }

    public function test_paid_at_order_puts_unpaid_last_then_by_due_date(): void
    {
        $this->assertSame([
            'CASE WHEN paid_at IS NULL THEN 1 ELSE 0 END',
            'date(paid_at) DESC',
            'CASE WHEN due_date IS NULL THEN 1 ELSE 0 END',
            'due_date ASC',
        ], FinanceListSort::orderBySql(FinanceListSort::PAID_AT));

        $this->assertSame([
            'CASE WHEN due_date IS NULL THEN 1 ELSE 0 END',
            'due_date ASC',
        ], FinanceListSort::orderBySql(FinanceListSort::DUE_DATE));
    }
}
