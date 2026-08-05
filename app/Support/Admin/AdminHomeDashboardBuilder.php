<?php

declare(strict_types=1);

namespace App\Support\Admin;

use App\Enums\CompanyNoticeAudience;
use App\Enums\FinancePayableStatus;
use App\Enums\HiringProcessStage;
use App\Enums\LandingInterestSource;
use App\Enums\StrategicCalendarItemKind;
use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use App\Models\CommercialSaleInstallment;
use App\Models\Company;
use App\Models\CompanyMethodology;
use App\Models\CompanyNotice;
use App\Models\FinancePayable;
use App\Models\HiringProcess;
use App\Models\LandingInterestSubmission;
use App\Models\StrategicCalendarItem;
use App\Models\Subscription;
use App\Models\TaskBoard;
use App\Models\TaskCard;
use App\Support\StrategicCalendarLeaveEnricher;
use App\Support\StrategicCalendarOccurrenceExpander;
use Carbon\Carbon;

final class AdminHomeDashboardBuilder
{
    /**
     * @return array{
     *     finance: array<string, mixed>,
     *     commercial: array<string, mixed>,
     *     operation_today: list<array<string, mixed>>,
     *     alerts_count: int,
     *     admin_tasks_open: int,
     *     kpis: array<string, mixed>,
     *     leads_by_source: list<array{key: string, label: string, count: int}>,
     *     funnel: list<array{key: string, label: string, count: int}>,
     *     monthly_goal: array{current_cents: int, goal_cents: int, percent: float}
     * }
     */
    public function build(): array
    {
        $today = Carbon::today()->startOfDay();
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();

        $receiveThisMonthCents = (int) CommercialSaleInstallment::query()
            ->where('status', '!=', CommercialSaleInstallment::STATUS_CANCELADO)
            ->whereBetween('due_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('amount_cents');

        $receivedThisMonthCents = (int) CommercialSaleInstallment::query()
            ->where('status', CommercialSaleInstallment::STATUS_PAGO)
            ->whereBetween('paid_at', [$monthStart, $monthEnd])
            ->sum('paid_amount_cents');

        $toReceiveCents = (int) CommercialSaleInstallment::query()
            ->where('status', CommercialSaleInstallment::STATUS_PENDENTE)
            ->sum('amount_cents');

        $payablesPendingCents = (int) FinancePayable::query()
            ->where('status', FinancePayableStatus::Pending)
            ->sum('amount_cents');

        $forecastCents = $receiveThisMonthCents - $payablesPendingCents;

        $leadsThisMonth = LandingInterestSubmission::query()
            ->where('created_at', '>=', $monthStart)
            ->count();

        $proposalsSentThisMonth = CommercialProposal::query()
            ->where('created_at', '>=', $monthStart)
            ->count();

        $proposalsInNegotiation = CommercialProposal::query()
            ->where('is_closed', false)
            ->count();

        $proposalsClosedThisMonth = CommercialProposal::query()
            ->where('is_closed', true)
            ->where('updated_at', '>=', $monthStart)
            ->count();

        $conversionRate = $leadsThisMonth > 0
            ? round(100 * $proposalsClosedThisMonth / $leadsThisMonth, 0)
            : ($proposalsSentThisMonth > 0
                ? round(100 * $proposalsClosedThisMonth / $proposalsSentThisMonth, 0)
                : 0.0);

        $avgTicketCents = (int) round((float) CommercialSale::query()
            ->where('status', '!=', CommercialSale::STATUS_CANCELADA)
            ->where('sold_at', '>=', $monthStart)
            ->avg('total_cents'));

        if ($avgTicketCents === 0) {
            $avgTicketCents = (int) round((float) CommercialProposal::query()
                ->where('is_closed', true)
                ->where('updated_at', '>=', $monthStart)
                ->avg('total_final_cents'));
        }

        $dayEnd = $today->copy()->endOfDay();
        $todayMasters = StrategicCalendarOccurrenceExpander::baseQueryForRange(
            StrategicCalendarItem::query()->with(['company:id,name', 'companies:id,name']),
            $today,
            $dayEnd,
        )->orderBy('occurs_on')->orderBy('id')->get();

        $operationToday = StrategicCalendarOccurrenceExpander::expandCollection(
            $todayMasters,
            $today,
            $dayEnd,
        );
        $operationToday = StrategicCalendarLeaveEnricher::enrich(
            $operationToday,
            $today,
            $dayEnd,
        )->take(6)->values()->map(static function (array $item): array {
            $time = self::extractTimeRange($item['description'] ?? null);

            return [
                'id' => $item['id'],
                'title' => $item['title'],
                'time' => $time['start'] ?? null,
                'kind' => is_array($item['kind'] ?? null) ? ($item['kind']['value'] ?? null) : ($item['kind'] ?? null),
                'company_name' => $item['company']['name'] ?? null,
            ];
        })->all();

        $alertsCount = $this->alertsCount($today, $monthStart);
        $adminTasksOpen = $this->adminOpenTasksCount();

        $activeCompanies = Company::query()->where('is_active', true)->count();
        $newCompaniesMonth = Company::query()
            ->where('created_at', '>=', $monthStart)
            ->count();
        $prevMonthStart = $monthStart->copy()->subMonth();
        $prevMonthEnd = $monthStart->copy()->subSecond();
        $newCompaniesPrevMonth = Company::query()
            ->whereBetween('created_at', [$prevMonthStart, $prevMonthEnd])
            ->count();
        $newCompaniesDeltaPct = $newCompaniesPrevMonth > 0
            ? (int) round(100 * ($newCompaniesMonth - $newCompaniesPrevMonth) / $newCompaniesPrevMonth)
            : ($newCompaniesMonth > 0 ? 100 : 0);

        $mrrCents = (int) Subscription::query()
            ->where('status', 'active')
            ->where(function ($q) use ($today) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $today->toDateString());
            })
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->sum('plans.price_monthly_cents');

        $revenueMonthCents = (int) CommercialSale::query()
            ->where('status', '!=', CommercialSale::STATUS_CANCELADA)
            ->whereBetween('sold_at', [$monthStart, $monthEnd])
            ->sum('total_cents');

        $goalCents = (int) config('talents.dashboard.monthly_revenue_goal_cents', 2_000_000);
        $goalPercent = $goalCents > 0 ? round(100 * $revenueMonthCents / $goalCents, 0) : 0.0;

        $openHiring = HiringProcess::query()
            ->where('current_stage', '!=', HiringProcessStage::Contratacao->value)
            ->count();
        $closedHiring = HiringProcess::query()
            ->where('current_stage', HiringProcessStage::Contratacao->value)
            ->count();

        $avgHiringDays = $this->averageHiringDays();

        $activeMethodology = CompanyMethodology::query()->where('is_active', true)->count();

        $leadsBySource = $this->leadsBySource($monthStart);

        return [
            'finance' => [
                'receive_this_month_cents' => $receiveThisMonthCents,
                'received_cents' => $receivedThisMonthCents,
                'to_receive_cents' => $toReceiveCents,
                'payables_cents' => $payablesPendingCents,
                'forecast_cents' => $forecastCents,
            ],
            'commercial' => [
                'leads_new' => $leadsThisMonth,
                'proposals_sent' => $proposalsSentThisMonth,
                'in_negotiation' => $proposalsInNegotiation,
                'closed' => $proposalsClosedThisMonth,
                'conversion_rate' => $conversionRate,
                'avg_ticket_cents' => $avgTicketCents,
            ],
            'operation_today' => $operationToday,
            'alerts_count' => $alertsCount,
            'admin_tasks_open' => $adminTasksOpen,
            'kpis' => [
                'active_clients' => $activeCompanies,
                'active_clients_delta' => $newCompaniesMonth,
                'new_clients_month' => $newCompaniesMonth,
                'new_clients_delta_pct' => $newCompaniesDeltaPct,
                'mrr_cents' => $mrrCents,
                'revenue_month_cents' => $revenueMonthCents,
                'revenue_goal_pct' => $goalPercent,
                'avg_hiring_days' => $avgHiringDays,
                'hiring_open' => $openHiring,
                'hiring_closed' => $closedHiring,
                'methodology_active' => $activeMethodology,
            ],
            'leads_by_source' => $leadsBySource,
            'funnel' => $this->funnel($leadsThisMonth, $proposalsInNegotiation, $proposalsClosedThisMonth, $monthStart),
            'monthly_goal' => [
                'current_cents' => $revenueMonthCents,
                'goal_cents' => $goalCents,
                'percent' => min(100.0, $goalPercent),
            ],
        ];
    }

    private function alertsCount(Carbon $today, Carbon $monthStart): int
    {
        return count($this->buildAlerts($today, $monthStart));
    }

    /**
     * @return list<array{id: string, label: string, tone: string}>
     */
    private function buildAlerts(Carbon $today, Carbon $monthStart): array
    {
        $alerts = [];

        $overdueInstallments = CommercialSaleInstallment::query()
            ->where('status', CommercialSaleInstallment::STATUS_PENDENTE)
            ->whereDate('due_date', '<', $today->toDateString())
            ->count();
        if ($overdueInstallments > 0) {
            $alerts[] = [
                'id' => 'overdue-installments',
                'label' => $overdueInstallments === 1
                    ? '1 parcela em atraso'
                    : "{$overdueInstallments} parcelas em atraso",
                'tone' => 'red',
            ];
        }

        $dueToday = CommercialProposal::query()
            ->where('is_closed', false)
            ->whereDate('updated_at', $today->toDateString())
            ->count();
        if ($dueToday > 0) {
            $alerts[] = [
                'id' => 'proposals-today',
                'label' => $dueToday === 1
                    ? '1 proposta movimentada hoje'
                    : "{$dueToday} propostas movimentadas hoje",
                'tone' => 'orange',
            ];
        }

        $payablesDueSoon = FinancePayable::query()
            ->where('status', FinancePayableStatus::Pending)
            ->whereBetween('due_date', [$today->toDateString(), $today->copy()->addDays(7)->toDateString()])
            ->count();
        if ($payablesDueSoon > 0) {
            $alerts[] = [
                'id' => 'payables-week',
                'label' => $payablesDueSoon === 1
                    ? '1 conta a pagar nos próximos 7 dias'
                    : "{$payablesDueSoon} contas a pagar nos próximos 7 dias",
                'tone' => 'yellow',
            ];
        }

        $openHiring = HiringProcess::query()
            ->where('current_stage', '!=', HiringProcessStage::Contratacao->value)
            ->count();
        if ($openHiring > 0) {
            $alerts[] = [
                'id' => 'hiring-open',
                'label' => $openHiring === 1
                    ? '1 contratação aberta'
                    : "{$openHiring} contratações abertas",
                'tone' => 'purple',
            ];
        }

        $newLeads = LandingInterestSubmission::query()
            ->where('created_at', '>=', $today->copy()->subDays(3))
            ->count();
        if ($newLeads > 0) {
            $alerts[] = [
                'id' => 'leads-recent',
                'label' => $newLeads === 1
                    ? '1 lead novo nos últimos 3 dias'
                    : "{$newLeads} leads novos nos últimos 3 dias",
                'tone' => 'green',
            ];
        }

        $unreadNotices = CompanyNotice::query()
            ->where('audience', CompanyNoticeAudience::Talents->value)
            ->where('created_at', '>=', $monthStart)
            ->count();
        if ($unreadNotices > 0 && count($alerts) < 6) {
            $alerts[] = [
                'id' => 'notices',
                'label' => 'Solicitações e avisos recentes no feed',
                'tone' => 'yellow',
            ];
        }

        return array_slice($alerts, 0, 6);
    }

    private function adminOpenTasksCount(): int
    {
        $boardIds = TaskBoard::query()->whereNull('company_id')->pluck('id');
        if ($boardIds->isEmpty()) {
            return 0;
        }

        return TaskCard::query()
            ->whereHas('list', fn ($q) => $q->whereIn('board_id', $boardIds))
            ->where('is_archived', false)
            ->whereNull('completed_at')
            ->count();
    }

    private function averageHiringDays(): ?int
    {
        $closed = HiringProcess::query()
            ->where('current_stage', HiringProcessStage::Contratacao->value)
            ->get(['created_at', 'updated_at']);

        if ($closed->isEmpty()) {
            return null;
        }

        $avg = $closed->avg(static fn (HiringProcess $p) => $p->created_at?->diffInDays($p->updated_at ?? now()) ?? 0);

        return (int) round((float) $avg);
    }

    /**
     * @return list<array{key: string, label: string, count: int}>
     */
    private function leadsBySource(Carbon $monthStart): array
    {
        $counts = LandingInterestSubmission::query()
            ->where('created_at', '>=', $monthStart)
            ->selectRaw('source, count(*) as c')
            ->groupBy('source')
            ->pluck('c', 'source');

        $rows = [];
        foreach (LandingInterestSource::cases() as $case) {
            $rows[] = [
                'key' => $case->value,
                'label' => $case->label(),
                'count' => (int) ($counts[$case->value] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * Funil a partir de leads, contatos (telefone), agenda do mês, propostas abertas e fechadas.
     *
     * @return list<array{key: string, label: string, count: int}>
     */
    private function funnel(int $leads, int $openProposals, int $closed, Carbon $monthStart): array
    {
        $contact = (int) LandingInterestSubmission::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->where('created_at', '>=', $monthStart)
            ->count();

        $meetings = (int) StrategicCalendarItem::query()
            ->where('occurs_on', '>=', $monthStart->toDateString())
            ->whereIn('kind', [
                StrategicCalendarItemKind::Event->value,
                StrategicCalendarItemKind::Ritual->value,
            ])
            ->count();

        return [
            ['key' => 'leads', 'label' => 'Leads', 'count' => $leads],
            ['key' => 'contact', 'label' => 'Contato', 'count' => $contact],
            ['key' => 'meeting', 'label' => 'Agenda', 'count' => $meetings],
            ['key' => 'proposal', 'label' => 'Proposta', 'count' => $openProposals],
            ['key' => 'closed', 'label' => 'Fechou', 'count' => $closed],
        ];
    }

    /**
     * @return array{start: string, end: string}|null
     */
    private static function extractTimeRange(?string $description): ?array
    {
        if ($description === null || $description === '') {
            return null;
        }

        if (! preg_match('/Hor[áa]rio:\s*(\d{1,2}:\d{2})\s*[–\-—]\s*(\d{1,2}:\d{2})/iu', $description, $m)) {
            return null;
        }

        return [
            'start' => str_pad($m[1], 5, '0', STR_PAD_LEFT),
            'end' => str_pad($m[2], 5, '0', STR_PAD_LEFT),
        ];
    }
}
