<?php

declare(strict_types=1);

namespace App\Support\Commercial;

use App\Models\CommercialProposal;
use Illuminate\Database\Eloquent\Builder;

/**
 * KPIs compactos do quadro de propostas (faixa acima do Kanban).
 */
final class ProposalBoardMetrics
{
    public const NO_CONTACT_DAYS = 7;

    /**
     * @param  Builder<CommercialProposal>  $base  Query já filtrada (sem status).
     * @param  int  $leadsCount  Contagem da coluna Leads (filtros da tela).
     * @return array{
     *     won_month_count: int,
     *     won_month_cents: int,
     *     total_count: int,
     *     leads_count: int,
     *     pipeline_open_cents: int,
     *     open_count: int,
     *     avg_ticket_open_cents: int,
     *     expiring_count: int,
     *     expired_count: int,
     *     no_contact_count: int,
     *     zapsign_pending_count: int
     * }
     */
    public static function build(Builder $base, int $validityDays, int $leadsCount = 0): array
    {
        $today = now();
        $monthStart = $today->copy()->startOfMonth();
        $validityDays = ProposalValidity::daysFromSettings($validityDays);

        $open = ProposalListStatus::applyFilter((clone $base), 'abertas');
        $closed = ProposalListStatus::applyFilter((clone $base), 'fechadas');

        $wonMonth = (clone $closed)->where('closed_at', '>=', $monthStart);
        $wonMonthCount = (clone $wonMonth)->count();
        $wonMonthCents = (int) (clone $wonMonth)->sum('total_final_cents');

        $totalCount = (clone $base)->count();
        $openCount = (clone $open)->count();
        $pipelineOpenCents = (int) (clone $open)->sum('total_final_cents');
        $avgTicketOpenCents = $openCount > 0 ? (int) round($pipelineOpenCents / $openCount) : 0;

        $expiringCutoff = ProposalValidity::createdAtCutoff($today, $validityDays);
        $expiringCount = (clone $open)
            ->whereDate('created_at', '<=', $expiringCutoff->toDateString())
            ->count();
        $expiredCount = (clone $open)
            ->whereDate('created_at', '<', $today->copy()->startOfDay()->subDays($validityDays)->toDateString())
            ->count();

        $noContactCount = (clone $open)
            ->whereNull('contacted_at')
            ->where('created_at', '<=', $today->copy()->subDays(self::NO_CONTACT_DAYS))
            ->count();

        $signedStatuses = ['signed', 'assinado', 'completed', 'concluido', 'concluído'];
        $zapsignPendingCount = (clone $base)
            ->whereHas('contracts', function (Builder $q): void {
                $q->where(function (Builder $sent): void {
                    $sent->whereNotNull('zapsign_document_token')
                        ->orWhereNotNull('zapsign_sent_at');
                });
            })
            ->whereDoesntHave('contracts', function (Builder $q) use ($signedStatuses): void {
                $q->whereIn('zapsign_status', $signedStatuses);
            })
            ->count();

        return [
            'won_month_count' => $wonMonthCount,
            'won_month_cents' => $wonMonthCents,
            'total_count' => $totalCount,
            'leads_count' => max(0, $leadsCount),
            'pipeline_open_cents' => $pipelineOpenCents,
            'open_count' => $openCount,
            'avg_ticket_open_cents' => $avgTicketOpenCents,
            'expiring_count' => $expiringCount,
            'expired_count' => $expiredCount,
            'no_contact_count' => $noContactCount,
            'zapsign_pending_count' => $zapsignPendingCount,
        ];
    }
}
