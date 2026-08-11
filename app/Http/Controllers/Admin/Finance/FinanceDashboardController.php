<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Enums\FinanceReceivableStatus;
use App\Http\Controllers\Controller;
use App\Models\CommercialCommission;
use App\Models\CommercialSale;
use App\Models\CommercialSaleInstallment;
use App\Models\FinanceReceivable;
use App\Support\Finance\FinanceCashflowMetrics;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinanceDashboardController extends Controller
{
    public function __construct(
        private readonly FinanceCashflowMetrics $cashflow,
    ) {}

    public function index(Request $request): Response
    {
        $period = $request->input('period', '90d');
        if (! in_array($period, ['30d', '90d', 'year', 'all'], true)) {
            $period = '90d';
        }

        [$start, $end] = $this->periodBounds($period);

        $upcomingInstallments = CommercialSaleInstallment::query()
            ->with(['sale:id,code,client_name'])
            ->where('status', CommercialSaleInstallment::STATUS_PENDENTE)
            ->orderBy('due_date')
            ->limit(10)
            ->get(['id', 'sale_id', 'number', 'amount_cents', 'due_date', 'method'])
            ->map(fn (CommercialSaleInstallment $i) => [
                'id' => 'sale-'.$i->id,
                'source' => 'sale',
                'sale_id' => $i->sale_id,
                'label' => ($i->sale?->code ?? 'Venda').' — '.($i->sale?->client_name ?? ''),
                'detail' => 'Parcela '.$i->number.' · '.($this->methodLabel($i->method)),
                'amount_cents' => $i->amount_cents,
                'due_date' => $i->due_date?->toDateString(),
                'href' => route('admin.financeiro.vendas.show', $i->sale_id),
            ]);

        $upcomingManual = FinanceReceivable::query()
            ->where('status', FinanceReceivableStatus::Pending)
            ->orderBy('due_date')
            ->limit(10)
            ->get(['id', 'title', 'payer_name', 'amount_cents', 'due_date'])
            ->map(fn (FinanceReceivable $r) => [
                'id' => 'manual-'.$r->id,
                'source' => 'manual',
                'sale_id' => null,
                'label' => $r->title,
                'detail' => $r->payer_name ?: 'Recebimento manual',
                'amount_cents' => $r->amount_cents,
                'due_date' => $r->due_date?->toDateString(),
                'href' => route('admin.financeiro.contas-a-receber.edit', $r),
            ]);

        $upcoming = $upcomingInstallments
            ->concat($upcomingManual)
            ->sortBy('due_date')
            ->take(10)
            ->values()
            ->all();

        $today = now()->toDateString();

        $overdueInstallments = CommercialSaleInstallment::query()
            ->with(['sale:id,code,client_name'])
            ->where('status', CommercialSaleInstallment::STATUS_PENDENTE)
            ->whereDate('due_date', '<', $today)
            ->orderBy('due_date')
            ->limit(10)
            ->get(['id', 'sale_id', 'number', 'amount_cents', 'due_date', 'method'])
            ->map(fn (CommercialSaleInstallment $i) => [
                'id' => 'sale-'.$i->id,
                'source' => 'sale',
                'sale_id' => $i->sale_id,
                'label' => ($i->sale?->code ?? 'Venda').' — '.($i->sale?->client_name ?? ''),
                'detail' => 'Parcela '.$i->number.' · '.($this->methodLabel($i->method)),
                'amount_cents' => $i->amount_cents,
                'due_date' => $i->due_date?->toDateString(),
                'href' => route('admin.financeiro.vendas.show', $i->sale_id),
            ]);

        $overdueManual = FinanceReceivable::query()
            ->where('status', FinanceReceivableStatus::Pending)
            ->whereDate('due_date', '<', $today)
            ->orderBy('due_date')
            ->limit(10)
            ->get(['id', 'title', 'payer_name', 'amount_cents', 'due_date'])
            ->map(fn (FinanceReceivable $r) => [
                'id' => 'manual-'.$r->id,
                'source' => 'manual',
                'sale_id' => null,
                'label' => $r->title,
                'detail' => $r->payer_name ?: 'Recebimento manual',
                'amount_cents' => $r->amount_cents,
                'due_date' => $r->due_date?->toDateString(),
                'href' => route('admin.financeiro.contas-a-receber.edit', $r),
            ]);

        $overdue = $overdueInstallments
            ->concat($overdueManual)
            ->sortBy('due_date')
            ->take(10)
            ->values()
            ->all();

        $commissionsQuery = CommercialCommission::query()
            ->where('status', CommercialCommission::STATUS_A_PAGAR);

        if ($start) {
            $commissionsQuery->where('created_at', '>=', $start);
        }
        if ($end) {
            $commissionsQuery->where('created_at', '<=', $end);
        }

        $commissionsPendingCents = (int) $commissionsQuery->sum('amount_cents');

        $recentSales = CommercialSale::query()
            ->with('seller:id,name')
            ->when($start, fn ($q) => $q->where('sold_at', '>=', $start))
            ->when($end, fn ($q) => $q->where('sold_at', '<=', $end))
            ->orderByDesc('sold_at')
            ->limit(10)
            ->get(['id', 'code', 'client_name', 'seller_id', 'total_cents', 'status', 'sold_at']);

        return Inertia::render('Admin/Finance/Dashboard', [
            'period' => $period,
            'kpis' => [
                'receivable_cents' => $this->cashflow->toReceiveCents(),
                'received_cents' => $this->cashflow->receivedInPeriodCents($start, $end),
                'overdue_cents' => $this->cashflow->overdueCents(),
                'commissions_pending_cents' => $commissionsPendingCents,
                'bank_balance_cents' => $this->cashflow->activeBankAccountsBalanceCents(),
                'bank_accounts_count' => $this->cashflow->activeBankAccountsCount(),
            ],
            'upcomingInstallments' => $upcoming,
            'overdueInstallments' => $overdue,
            'recentSales' => $recentSales,
        ]);
    }

    private function methodLabel(?string $method): string
    {
        return match ($method) {
            'pix' => 'PIX',
            'boleto' => 'Boleto',
            'cartao' => 'Cartão',
            default => $method ?? '—',
        };
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function periodBounds(string $period): array
    {
        $end = Carbon::now();

        return match ($period) {
            '30d' => [Carbon::now()->subDays(30), $end],
            '90d' => [Carbon::now()->subDays(90), $end],
            'year' => [Carbon::now()->subYear(), $end],
            default => [null, null],
        };
    }
}
