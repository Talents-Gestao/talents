<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Models\CommercialCommission;
use App\Models\CommercialSale;
use App\Models\CommercialSaleInstallment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinanceDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $period = $request->input('period', '90d');
        if (! in_array($period, ['30d', '90d', 'year', 'all'], true)) {
            $period = '90d';
        }

        [$start, $end] = $this->periodBounds($period);

        $installmentsQuery = CommercialSaleInstallment::query()
            ->where('status', CommercialSaleInstallment::STATUS_PENDENTE);

        $receivableCents = (int) (clone $installmentsQuery)->sum('amount_cents');

        $overdueCents = (int) CommercialSaleInstallment::query()
            ->where('status', CommercialSaleInstallment::STATUS_PENDENTE)
            ->whereDate('due_date', '<', now()->toDateString())
            ->sum('amount_cents');

        $receivedQuery = CommercialSaleInstallment::query()
            ->where('status', CommercialSaleInstallment::STATUS_PAGO);

        if ($start) {
            $receivedQuery->where('paid_at', '>=', $start);
        }
        if ($end) {
            $receivedQuery->where('paid_at', '<=', $end);
        }

        $receivedCents = (int) $receivedQuery->sum('paid_amount_cents');

        $commissionsQuery = CommercialCommission::query()
            ->where('status', CommercialCommission::STATUS_A_PAGAR);

        if ($start) {
            $commissionsQuery->where('created_at', '>=', $start);
        }
        if ($end) {
            $commissionsQuery->where('created_at', '<=', $end);
        }

        $commissionsPendingCents = (int) $commissionsQuery->sum('amount_cents');

        $upcomingInstallments = CommercialSaleInstallment::query()
            ->with(['sale:id,code,client_name'])
            ->where('status', CommercialSaleInstallment::STATUS_PENDENTE)
            ->orderBy('due_date')
            ->limit(10)
            ->get(['id', 'sale_id', 'number', 'amount_cents', 'due_date', 'method']);

        $overdueInstallments = CommercialSaleInstallment::query()
            ->with(['sale:id,code,client_name'])
            ->where('status', CommercialSaleInstallment::STATUS_PENDENTE)
            ->whereDate('due_date', '<', now()->toDateString())
            ->orderBy('due_date')
            ->limit(10)
            ->get(['id', 'sale_id', 'number', 'amount_cents', 'due_date', 'method']);

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
                'receivable_cents' => $receivableCents,
                'received_cents' => $receivedCents,
                'overdue_cents' => $overdueCents,
                'commissions_pending_cents' => $commissionsPendingCents,
            ],
            'upcomingInstallments' => $upcomingInstallments,
            'overdueInstallments' => $overdueInstallments,
            'recentSales' => $recentSales,
        ]);
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
