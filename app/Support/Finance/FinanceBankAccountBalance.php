<?php

declare(strict_types=1);

namespace App\Support\Finance;

use App\Enums\FinancePayableStatus;
use App\Enums\FinanceReceivableStatus;
use App\Models\CommercialSaleInstallment;
use App\Models\FinanceBankAccount;
use App\Models\FinancePayable;
use App\Models\FinanceReceivable;

/**
 * Saldo atual da conta = saldo inicial
 * + recebimentos manuais pagos nesta conta
 * + parcelas de venda pagas nesta conta
 * − contas a pagar pagas nesta conta.
 *
 * Só movimentos pagos/recebidos entram; pendentes não movem caixa.
 */
final class FinanceBankAccountBalance
{
    public function currentBalanceCents(FinanceBankAccount $account): int
    {
        $id = (int) $account->id;

        $receivedManual = (int) FinanceReceivable::query()
            ->where('bank_account_id', $id)
            ->where('status', FinanceReceivableStatus::Paid)
            ->selectRaw('coalesce(sum(coalesce(paid_amount_cents, amount_cents)), 0) as total')
            ->value('total');

        $receivedInstallments = (int) CommercialSaleInstallment::query()
            ->where('bank_account_id', $id)
            ->where('status', CommercialSaleInstallment::STATUS_PAGO)
            ->selectRaw('coalesce(sum(coalesce(paid_amount_cents, amount_cents)), 0) as total')
            ->value('total');

        $paidOut = (int) FinancePayable::query()
            ->where('bank_account_id', $id)
            ->where('status', FinancePayableStatus::Paid)
            ->selectRaw('coalesce(sum(coalesce(paid_amount_cents, amount_cents)), 0) as total')
            ->value('total');

        return (int) $account->initial_balance_cents
            + $receivedManual
            + $receivedInstallments
            - $paidOut;
    }

    /**
     * Soma dos saldos atuais das contas ativas (para KPIs / resumo).
     */
    public function activeAccountsTotalCents(): int
    {
        $total = 0;
        foreach (
            FinanceBankAccount::query()->where('is_active', true)->get(['id', 'initial_balance_cents']) as $account
        ) {
            $total += $this->currentBalanceCents($account);
        }

        return $total;
    }
}
