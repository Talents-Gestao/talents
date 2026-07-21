<?php

namespace App\Http\Controllers\Admin\Commercial;

use App\Http\Controllers\Controller;
use App\Models\CommercialProposal;
use App\Models\CommercialProposalProductLine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $period = $request->input('period', '90d');
        if (! in_array($period, ['30d', '90d', 'year', 'all'], true)) {
            $period = '90d';
        }

        [$start, $end] = $this->currentPeriodBounds($period);
        [$prevStart, $prevEnd] = $this->previousPeriodBounds($period);

        $kpis = $this->kpisForPeriod($start, $end);
        $kpisPrev = ($prevStart !== null && $prevEnd !== null)
            ? $this->kpisForPeriod($prevStart, $prevEnd)
            : null;

        $deltas = $this->buildDeltas($kpis, $kpisPrev);

        $pipelineOpenCents = (int) CommercialProposal::query()
            ->where('is_closed', false)
            ->sum('total_final_cents');

        $monthlyClosings = $this->monthlyClosingsLastSix();

        $pendingProposals = CommercialProposal::query()
            ->where('is_closed', false)
            ->where('created_at', '<', now()->subDays(30))
            ->with('seller:id,name')
            ->orderBy('created_at')
            ->limit(15)
            ->get(['id', 'code', 'client_name', 'seller_id', 'total_final_cents', 'created_at']);

        $byService = $this->summaryByService($start, $end);
        $bySeller = $this->summaryBySeller($start, $end);

        $recent = CommercialProposal::query()
            ->when($start, fn (Builder $q) => $q->where('created_at', '>=', $start))
            ->when($end, fn (Builder $q) => $q->where('created_at', '<=', $end))
            ->with('seller:id,name')
            ->latest()
            ->limit(10)
            ->get(['id', 'code', 'client_name', 'seller_id', 'employee_count', 'total_final_cents', 'is_closed', 'created_at']);

        return Inertia::render('Admin/Commercial/Dashboard', [
            'period' => $period,
            'kpis' => array_merge($kpis, [
                'pipeline_open_cents' => $pipelineOpenCents,
            ]),
            'deltas' => $deltas,
            'byService' => $byService,
            'bySeller' => $bySeller,
            'recent' => $recent,
            'monthlyClosings' => $monthlyClosings,
            'pendingProposals' => $pendingProposals,
        ]);
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function currentPeriodBounds(string $period): array
    {
        $end = Carbon::now();

        return match ($period) {
            '30d' => [Carbon::now()->subDays(30), $end],
            '90d' => [Carbon::now()->subDays(90), $end],
            'year' => [Carbon::now()->subYear(), $end],
            default => [null, null],
        };
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function previousPeriodBounds(string $period): array
    {
        if ($period === 'all') {
            return [null, null];
        }

        return match ($period) {
            '30d' => [Carbon::now()->subDays(60), Carbon::now()->subDays(30)],
            '90d' => [Carbon::now()->subDays(180), Carbon::now()->subDays(90)],
            'year' => [Carbon::now()->subYears(2), Carbon::now()->subYear()],
            default => [null, null],
        };
    }

    /**
     * @return array<string, int|float>
     */
    private function kpisForPeriod(?Carbon $start, ?Carbon $end): array
    {
        $createdBase = CommercialProposal::query()
            ->when($start, fn (Builder $q) => $q->where('created_at', '>=', $start))
            ->when($end, fn (Builder $q) => $q->where('created_at', '<=', $end));

        $totalCount = (clone $createdBase)->count();
        $totalBudgetCents = (int) (clone $createdBase)->sum('total_final_cents');
        $avgTicketCents = $totalCount > 0 ? (int) round($totalBudgetCents / $totalCount) : 0;

        $closedByDateBase = CommercialProposal::query()
            ->where('is_closed', true)
            ->when($start, fn (Builder $q) => $q->where('closed_at', '>=', $start))
            ->when($end, fn (Builder $q) => $q->where('closed_at', '<=', $end));

        $closedCount = (clone $closedByDateBase)->count();
        $totalClosedCents = (int) (clone $closedByDateBase)->sum('total_final_cents');
        $commissionTotalCents = (int) (clone $closedByDateBase)->sum('commission_cents');

        $closedAmongCreated = (clone $createdBase)->where('is_closed', true)->count();
        $conversionRate = $totalCount > 0 ? round(100 * $closedAmongCreated / $totalCount, 1) : 0.0;

        return [
            'total_count' => $totalCount,
            'closed_count' => $closedCount,
            'total_budget_cents' => $totalBudgetCents,
            'total_closed_cents' => $totalClosedCents,
            'avg_ticket_cents' => $avgTicketCents,
            'conversion_rate' => $conversionRate,
            'commission_total_cents' => $commissionTotalCents,
        ];
    }

    /**
     * @param  array<string, int|float>  $curr
     * @param  array<string, int|float>|null  $prev
     * @return array<string, float|null>
     */
    private function buildDeltas(array $curr, ?array $prev): array
    {
        if ($prev === null) {
            return [
                'total_count' => null,
                'closed_count' => null,
                'total_budget_cents' => null,
                'total_closed_cents' => null,
                'avg_ticket_cents' => null,
                'conversion_rate' => null,
                'commission_total_cents' => null,
            ];
        }

        $pct = function (int|float $c, int|float $p): ?float {
            if ($p == 0) {
                return $c == 0 ? 0.0 : null;
            }

            return round(100 * ($c - $p) / $p, 1);
        };

        return [
            'total_count' => $pct($curr['total_count'], $prev['total_count']),
            'closed_count' => $pct($curr['closed_count'], $prev['closed_count']),
            'total_budget_cents' => $pct($curr['total_budget_cents'], $prev['total_budget_cents']),
            'total_closed_cents' => $pct($curr['total_closed_cents'], $prev['total_closed_cents']),
            'avg_ticket_cents' => $pct($curr['avg_ticket_cents'], $prev['avg_ticket_cents']),
            'conversion_rate' => $curr['conversion_rate'] - $prev['conversion_rate'],
            'commission_total_cents' => $pct($curr['commission_total_cents'], $prev['commission_total_cents']),
        ];
    }

    /**
     * @return array<int, array{month: string, label: string, count: int, total_cents: int}>
     */
    private function monthlyClosingsLastSix(): array
    {
        $out = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i)->startOfMonth();
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth()->endOfDay();

            $count = CommercialProposal::query()
                ->where('is_closed', true)
                ->whereBetween('closed_at', [$start, $end])
                ->count();

            $totalCents = (int) CommercialProposal::query()
                ->where('is_closed', true)
                ->whereBetween('closed_at', [$start, $end])
                ->sum('total_final_cents');

            $out[] = [
                'month' => $month->format('Y-m'),
                'label' => $month->translatedFormat('M/y'),
                'count' => $count,
                'total_cents' => $totalCents,
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function summaryByService(?Carbon $start, ?Carbon $end): array
    {
        $budgetStats = $this->productLineStats($start, $end, false);
        $closedStats = $this->productLineStats($start, $end, true);

        $labels = array_values(array_unique(array_merge(
            array_keys($budgetStats),
            array_keys($closedStats),
        )));
        sort($labels);

        $rows = [];
        foreach ($labels as $label) {
            $budget = $budgetStats[$label] ?? ['proposal_count' => 0, 'total_cents' => 0];
            $closed = $closedStats[$label] ?? ['proposal_count' => 0, 'total_cents' => 0];
            $budgetCount = (int) $budget['proposal_count'];
            $closedAmongBudget = $this->closedAmongBudgetForProductLabel($label, $start, $end);

            $rows[] = [
                'label' => $label,
                'budget_count' => $budgetCount,
                'budget_total_cents' => (int) $budget['total_cents'],
                'closed_count' => (int) $closed['proposal_count'],
                'closed_total_cents' => (int) $closed['total_cents'],
                'conversion_rate' => $budgetCount > 0
                    ? round(100 * $closedAmongBudget / $budgetCount, 1)
                    : 0.0,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, array{proposal_count: int, total_cents: int}>
     */
    private function productLineStats(?Carbon $start, ?Carbon $end, bool $onlyClosed): array
    {
        $query = CommercialProposalProductLine::query()
            ->join('commercial_proposals as p', 'p.id', '=', 'commercial_proposal_product_lines.commercial_proposal_id')
            ->selectRaw('commercial_proposal_product_lines.label_snapshot as label')
            ->selectRaw('COUNT(DISTINCT commercial_proposal_product_lines.commercial_proposal_id) as proposal_count')
            ->selectRaw('SUM(commercial_proposal_product_lines.total_cents) as total_cents')
            ->groupBy('commercial_proposal_product_lines.label_snapshot');

        if ($onlyClosed) {
            $query->where('p.is_closed', true)
                ->when($start, fn (Builder $q) => $q->where('p.closed_at', '>=', $start))
                ->when($end, fn (Builder $q) => $q->where('p.closed_at', '<=', $end));
        } else {
            $query->when($start, fn (Builder $q) => $q->where('p.created_at', '>=', $start))
                ->when($end, fn (Builder $q) => $q->where('p.created_at', '<=', $end));
        }

        $out = [];
        foreach ($query->get() as $row) {
            $out[(string) $row->label] = [
                'proposal_count' => (int) $row->proposal_count,
                'total_cents' => (int) $row->total_cents,
            ];
        }

        return $out;
    }

    private function closedAmongBudgetForProductLabel(string $label, ?Carbon $start, ?Carbon $end): int
    {
        return (int) CommercialProposalProductLine::query()
            ->join('commercial_proposals as p', 'p.id', '=', 'commercial_proposal_product_lines.commercial_proposal_id')
            ->where('commercial_proposal_product_lines.label_snapshot', $label)
            ->where('p.is_closed', true)
            ->when($start, fn (Builder $q) => $q->where('p.created_at', '>=', $start))
            ->when($end, fn (Builder $q) => $q->where('p.created_at', '<=', $end))
            ->distinct('commercial_proposal_product_lines.commercial_proposal_id')
            ->count('commercial_proposal_product_lines.commercial_proposal_id');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function summaryBySeller(?Carbon $start, ?Carbon $end): array
    {
        $sellers = User::query()
            ->where('is_commercial', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $rows = $sellers->map(function (User $seller) use ($start, $end) {
            $budgetBase = CommercialProposal::query()
                ->where('seller_id', $seller->id)
                ->when($start, fn (Builder $q) => $q->where('created_at', '>=', $start))
                ->when($end, fn (Builder $q) => $q->where('created_at', '<=', $end));

            $closedBase = CommercialProposal::query()
                ->where('seller_id', $seller->id)
                ->where('is_closed', true)
                ->when($start, fn (Builder $q) => $q->where('closed_at', '>=', $start))
                ->when($end, fn (Builder $q) => $q->where('closed_at', '<=', $end));

            $budgetCount = (clone $budgetBase)->count();
            $closedAmongCreated = (clone $budgetBase)->where('is_closed', true)->count();
            $conversionRate = $budgetCount > 0 ? round(100 * $closedAmongCreated / $budgetCount, 1) : 0.0;

            return [
                'seller_id' => $seller->id,
                'name' => $seller->name,
                'budget_count' => $budgetCount,
                'budget_total_cents' => (int) (clone $budgetBase)->sum('total_final_cents'),
                'closed_count' => (clone $closedBase)->count(),
                'closed_total_cents' => (int) (clone $closedBase)->sum('total_final_cents'),
                'commission_total_cents' => (int) (clone $closedBase)->sum('commission_cents'),
                'conversion_rate' => $conversionRate,
            ];
        })->all();

        usort($rows, fn ($a, $b) => $b['closed_total_cents'] <=> $a['closed_total_cents']);

        return $rows;
    }
}
