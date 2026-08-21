<?php

declare(strict_types=1);

namespace App\Support\Finance;

use App\Enums\FinanceReceivableStatus;
use App\Models\CommercialSaleInstallment;
use App\Models\FinanceBankAccount;
use App\Models\FinanceReceivable;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Agrega métricas financeiras partilhadas entre Home, Resumo Financeiro e listagens.
 * Combina parcelas de vendas (origem comercial) com contas a receber manuais.
 */
class FinanceCashflowMetrics
{
    /**
     * Total ainda a receber (parcelas pendentes + receivables manuais pendentes).
     */
    public function toReceiveCents(): int
    {
        return $this->pendingInstallmentsCents() + $this->pendingManualCents();
    }

    /**
     * Valores com vencimento no intervalo (pipeline do mês), exclui cancelados.
     * Alinha-se ao card Home «Receber este mês».
     */
    public function scheduledDueBetweenCents(CarbonInterface $start, CarbonInterface $end): int
    {
        $installments = (int) CommercialSaleInstallment::query()
            ->where('status', '!=', CommercialSaleInstallment::STATUS_CANCELADO)
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->sum('amount_cents');

        $manual = (int) FinanceReceivable::query()
            ->where('status', '!=', FinanceReceivableStatus::Cancelled)
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->sum('amount_cents');

        return $installments + $manual;
    }

    /**
     * Apenas pendentes com vencimento no intervalo.
     */
    public function pendingDueBetweenCents(CarbonInterface $start, CarbonInterface $end): int
    {
        $installments = (int) CommercialSaleInstallment::query()
            ->where('status', CommercialSaleInstallment::STATUS_PENDENTE)
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->sum('amount_cents');

        $manual = (int) FinanceReceivable::query()
            ->where('status', FinanceReceivableStatus::Pending)
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->sum('amount_cents');

        return $installments + $manual;
    }

    /**
     * Valores efetivamente recebidos no intervalo (paid_at).
     */
    public function receivedBetweenCents(CarbonInterface $start, CarbonInterface $end): int
    {
        $installments = (int) CommercialSaleInstallment::query()
            ->where('status', CommercialSaleInstallment::STATUS_PAGO)
            ->whereBetween('paid_at', [$start, $end])
            ->sum('paid_amount_cents');

        $manual = (int) FinanceReceivable::query()
            ->where('status', FinanceReceivableStatus::Paid)
            ->whereBetween('paid_at', [$start, $end])
            ->sum('paid_amount_cents');

        return $installments + $manual;
    }

    /**
     * Recebido no período opcional (null = sem limite inferior/superior).
     */
    public function receivedInPeriodCents(?CarbonInterface $start, ?CarbonInterface $end): int
    {
        $installmentsQuery = CommercialSaleInstallment::query()
            ->where('status', CommercialSaleInstallment::STATUS_PAGO);

        $manualQuery = FinanceReceivable::query()
            ->where('status', FinanceReceivableStatus::Paid);

        if ($start) {
            $installmentsQuery->where('paid_at', '>=', $start);
            $manualQuery->where('paid_at', '>=', $start);
        }
        if ($end) {
            $installmentsQuery->where('paid_at', '<=', $end);
            $manualQuery->where('paid_at', '<=', $end);
        }

        return (int) $installmentsQuery->sum('paid_amount_cents')
            + (int) $manualQuery->sum('paid_amount_cents');
    }

    public function overdueCents(?CarbonInterface $asOf = null): int
    {
        $today = ($asOf ?? Carbon::now())->toDateString();

        $installments = (int) CommercialSaleInstallment::query()
            ->where('status', CommercialSaleInstallment::STATUS_PENDENTE)
            ->whereDate('due_date', '<', $today)
            ->sum('amount_cents');

        $manual = (int) FinanceReceivable::query()
            ->where('status', FinanceReceivableStatus::Pending)
            ->whereDate('due_date', '<', $today)
            ->sum('amount_cents');

        return $installments + $manual;
    }

    /**
     * Soma dos saldos atuais das contas bancárias ativas
     * (saldo inicial ± movimentos pagos/recebidos vinculados).
     */
    public function activeBankAccountsBalanceCents(): int
    {
        return app(FinanceBankAccountBalance::class)->activeAccountsTotalCents();
    }

    public function activeBankAccountsCount(): int
    {
        return (int) FinanceBankAccount::query()->where('is_active', true)->count();
    }

    private function pendingInstallmentsCents(): int
    {
        return (int) CommercialSaleInstallment::query()
            ->where('status', CommercialSaleInstallment::STATUS_PENDENTE)
            ->sum('amount_cents');
    }

    private function pendingManualCents(): int
    {
        return (int) FinanceReceivable::query()
            ->where('status', FinanceReceivableStatus::Pending)
            ->sum('amount_cents');
    }
}
