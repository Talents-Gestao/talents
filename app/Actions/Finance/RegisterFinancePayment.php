<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Actions\Notices\PublishCommercialNotice;
use App\Models\CommercialSaleInstallment;
use App\Models\FinancePayable;
use App\Models\FinanceReceivable;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Baixa (pagamento/recebimento) de parcela de venda, conta a receber manual ou
 * conta a pagar, com a data informada pelo usuário.
 *
 * Só baixa o que ainda não está pago; para corrigir a data de um item pago use
 * ChangeFinancePaymentDate. Na parcela de venda, recalcula o status da venda e
 * publica o aviso «Parcela recebida» (uma única vez, na transição).
 */
final class RegisterFinancePayment
{
    public function __construct(
        private readonly PublishCommercialNotice $notices,
    ) {}

    public function handle(
        CommercialSaleInstallment|FinanceReceivable|FinancePayable $entry,
        CarbonInterface $paidAt,
        ?int $bankAccountId,
        ?int $paymentMethodId = null,
        ?int $paidAmountCents = null,
        ?User $actor = null,
    ): void {
        if ($entry->isPaid()) {
            return;
        }

        DB::transaction(function () use ($entry, $paidAt, $bankAccountId, $paymentMethodId, $paidAmountCents): void {
            $entry->markPaid(
                paidAt: $paidAt,
                paidAmountCents: $paidAmountCents,
                paymentMethodId: $paymentMethodId,
                bankAccountId: $bankAccountId,
            );

            if ($entry instanceof CommercialSaleInstallment) {
                $entry->sale?->recalculateStatus();
            }
        });

        if ($entry instanceof CommercialSaleInstallment) {
            $this->notices->installmentPaid($entry->fresh('sale'), $actor);
        }
    }
}
