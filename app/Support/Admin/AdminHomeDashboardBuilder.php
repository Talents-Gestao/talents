<?php

declare(strict_types=1);

namespace App\Support\Admin;

use App\Enums\FinancePayableStatus;
use App\Enums\HiringProcessStage;
use App\Enums\LandingInterestSource;
use App\Models\AdminDashboardSettings;
use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use App\Models\Company;
use App\Models\CompanyMethodology;
use App\Models\FinancePayable;
use App\Models\HiringProcess;
use App\Models\LandingInterestSubmission;
use App\Models\StrategicCalendarItem;
use App\Models\Subscription;
use App\Models\TaskBoard;
use App\Models\TaskCard;
use App\Models\User;
use App\Support\Commercial\ProposalListStatus;
use App\Support\Finance\FinanceCashflowMetrics;
use App\Support\StrategicCalendarLeaveEnricher;
use App\Support\StrategicCalendarOccurrenceExpander;
use Carbon\Carbon;

final class AdminHomeDashboardBuilder
{
    /**
     * @return array{
     *     finance: array<string, mixed>,
     *     operation_today: list<array<string, mixed>>,
     *     tasks_today: list<array<string, mixed>>,
     *     admin_tasks_open: int,
     *     kpis: array<string, mixed>,
     *     leads_by_source: list<array{key: string, label: string, count: int}>,
     *     leads_this_month: int,
     *     funnel: list<array{key: string, label: string, count: int}>,
     *     monthly_goal: array{current_cents: int, goal_cents: int, percent: float}
     * }
     */
    public function build(?User $viewer = null): array
    {
        $today = Carbon::today()->startOfDay();
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();

        $cashflow = app(FinanceCashflowMetrics::class);

        $receiveThisMonthCents = $cashflow->scheduledDueBetweenCents($monthStart, $monthEnd);
        $receivedThisMonthCents = $cashflow->receivedBetweenCents($monthStart, $monthEnd);
        $toReceiveCents = $cashflow->toReceiveCents();

        $payablesPendingCents = (int) FinancePayable::query()
            ->where('status', FinancePayableStatus::Pending)
            ->sum('amount_cents');

        $forecastCents = $receiveThisMonthCents - $payablesPendingCents;

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

        $adminTasksOpen = $this->adminOpenTasksCount();
        $tasksToday = $this->adminTasksToday($today, $viewer);

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

        // Meta mensal — realizado: só vendas com sold_at no mês corrente.
        // Pontuais: total_cents. Recorrentes: apenas a parcela mensal (não o total do período).
        $revenueMonthCents = $this->monthlyGoalRevenueCents($monthStart, $monthEnd);

        $goalCents = AdminDashboardSettings::resolvedMonthlyRevenueGoalCents();
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
        $leadsThisMonth = (int) collect($leadsBySource)->sum('count');

        return [
            'finance' => [
                'receive_this_month_cents' => $receiveThisMonthCents,
                'received_cents' => $receivedThisMonthCents,
                'to_receive_cents' => $toReceiveCents,
                'payables_cents' => $payablesPendingCents,
                'forecast_cents' => $forecastCents,
            ],
            'operation_today' => $operationToday,
            'tasks_today' => $tasksToday,
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
            // Contexto do mês: não entra nas % do funil (unidade ≠ proposta).
            'leads_this_month' => $leadsThisMonth,
            'funnel' => $this->proposalFunnelForMonth($monthStart, $monthEnd),
            'monthly_goal' => [
                'current_cents' => $revenueMonthCents,
                'goal_cents' => $goalCents,
                'percent' => min(100.0, $goalPercent),
            ],
        ];
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

    /**
     * Realizado da Meta mensal no intervalo [monthStart, monthEnd] (inclusive).
     * Usa sold_at; vendas canceladas ficam de fora; recorrentes contam só a parcela mensal.
     */
    private function monthlyGoalRevenueCents(Carbon $monthStart, Carbon $monthEnd): int
    {
        $sales = CommercialSale::query()
            ->where('status', '!=', CommercialSale::STATUS_CANCELADA)
            ->whereBetween('sold_at', [$monthStart, $monthEnd])
            ->get([
                'total_cents',
                'is_recurring',
                'recurring_months',
                'recurring_monthly_cents',
            ]);

        return (int) $sales->sum(
            static fn (CommercialSale $sale): int => $sale->monthlyGoalContributionCents(),
        );
    }

    /**
     * Tarefas Admin (boards sem company_id) atribuídas ao utilizador, relevantes para o dia:
     * membro do cartão, due_date <= hoje, abertas (não arquivadas / não concluídas). Limite 6.
     * Sem due_date ou sem atribuição não entram.
     *
     * @return list<array{id: int, title: string, list_name: string|null, board_name: string|null, due_date: string}>
     */
    private function adminTasksToday(Carbon $today, ?User $viewer): array
    {
        $userId = $viewer?->id;
        if (! $userId) {
            return [];
        }

        $boardIds = TaskBoard::query()->whereNull('company_id')->pluck('id');
        if ($boardIds->isEmpty()) {
            return [];
        }

        $todayStr = $today->toDateString();

        return TaskCard::query()
            ->with(['list:id,board_id,name', 'list.board:id,name'])
            ->whereHas('list', fn ($q) => $q->whereIn('board_id', $boardIds))
            ->whereHas('members', fn ($q) => $q->where('users.id', $userId))
            ->where('is_archived', false)
            ->whereNull('completed_at')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $todayStr)
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit(6)
            ->get()
            ->map(static function (TaskCard $card) use ($todayStr): array {
                return [
                    'id' => (int) $card->id,
                    'title' => (string) $card->title,
                    'list_name' => $card->list?->name,
                    'board_name' => $card->list?->board?->name,
                    'due_date' => $card->due_date?->toDateString() ?? $todayStr,
                ];
            })
            ->all();
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
     * Funil comercial (cohort do mês): propostas criadas no mês corrente.
     *
     * Unidade = CommercialProposal com created_at no intervalo do mês.
     * % de cada etapa = count / tamanho do cohort (etapa «Proposta» = 100%).
     * Leads e reuniões de calendário NÃO entram neste funil (leads ficam no widget de origem).
     *
     * Etapas (não são necessariamente exclusivas — uma proposta aprovada com venda conta em ambas):
     * - Proposta: todas do cohort
     * - Negociação: ProposalListStatus::NEGOTIATION
     * - Aprovada: ProposalListStatus::APPROVED (ou is_closed legado)
     * - Venda: tem CommercialSale vinculada
     * - Encerrada: ProposalListStatus::ENDED (perda / fora do pipeline)
     *
     * Nota sobre closed_at: a data de negócio do fechamento é closed_at. Neste funil (cohort por
     * created_at), «Aprovada» usa o status atual da proposta do cohort — tipicamente criada e
     * aprovada no mesmo mês. Uma métrica separada «fechou no mês» (propostas de qualquer cohort
     * com closed_at no mês) pode usar closed_at; não misturar com este funil.
     *
     * @return list<array{key: string, label: string, count: int}>
     */
    private function proposalFunnelForMonth(Carbon $monthStart, Carbon $monthEnd): array
    {
        $cohort = CommercialProposal::query()
            ->with(['sale:id,proposal_id,status'])
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->get(['id', 'is_closed', 'list_status', 'closed_at']);

        $cohortSize = $cohort->count();

        $negotiation = 0;
        $approved = 0;
        $withSale = 0;
        $ended = 0;

        foreach ($cohort as $proposal) {
            $status = ProposalListStatus::for($proposal);

            if ($status === ProposalListStatus::NEGOTIATION) {
                $negotiation++;
            }
            if ($status === ProposalListStatus::APPROVED || $proposal->is_closed) {
                $approved++;
            }
            if ($proposal->sale !== null) {
                $withSale++;
            }
            if ($status === ProposalListStatus::ENDED) {
                $ended++;
            }
        }

        return [
            ['key' => 'proposal', 'label' => 'Proposta', 'count' => $cohortSize],
            ['key' => 'negotiation', 'label' => 'Negociação', 'count' => $negotiation],
            ['key' => 'approved', 'label' => 'Aprovada', 'count' => $approved],
            ['key' => 'sale', 'label' => 'Venda', 'count' => $withSale],
            ['key' => 'ended', 'label' => 'Encerrada', 'count' => $ended],
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
