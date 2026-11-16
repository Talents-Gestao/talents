<?php

declare(strict_types=1);

namespace App\Actions\Commercial;

use App\Actions\Notices\PublishCommercialNotice;
use App\Models\CommercialCommission;
use App\Models\CommercialCommissionRefund;
use App\Models\CommercialCommissionRefundEntry;
use App\Models\CommercialSale;
use App\Services\Commercial\CommissionPayoutCalculator;

/**
 * Recalcula os campos derivados das comissões de uma venda:
 * teto (base/amount) a partir das parcelas não canceladas, pago líquido (pagamentos − devoluções
 * registradas), status e paid_at. Em seguida concilia as devoluções:
 *
 * - Teto abaixo do pago: cria devolução pendente (pago − teto, descontado o que já está em cobrança
 *   ou foi assumido como perda) e avisa a equipe (commission_refund_due). Nada é assumido como
 *   perda automaticamente.
 * - Teto voltou a cobrir o pago, ou o cliente pagou o adiantado em cobrança: a parte que deixou de
 *   ser devida é cancelada automaticamente, com registro no histórico.
 *
 * Comissão com perda assumida pela regra antiga (loss_notified_at) não vira devolução: dados antigos
 * não mudam. Comissão sem percentual (legado) mantém o valor gravado.
 */
final class RefreshCommissionPayout
{
    public function __construct(
        private readonly PublishCommercialNotice $notices,
    ) {}

    public function forSale(CommercialSale $sale): void
    {
        $installments = $sale->installments()->get();
        $totalCents = CommissionPayoutCalculator::saleTotalCents($sale, $installments);

        $sale->commissions()->get()->each(
            fn (CommercialCommission $commission) => $this->apply($commission, $sale, $totalCents),
        );
    }

    public function forCommission(CommercialCommission $commission): void
    {
        $sale = $commission->sale()->first();
        if (! $sale) {
            return;
        }

        $this->apply(
            $commission,
            $sale,
            CommissionPayoutCalculator::saleTotalCents($sale, $sale->installments()->get()),
        );
    }

    private function apply(CommercialCommission $commission, CommercialSale $sale, int $saleTotalCents): void
    {
        $percent = (float) $commission->percent;

        if ($percent > 0) {
            $commission->base_cents = $saleTotalCents;
            $commission->amount_cents = CommissionPayoutCalculator::ceilingCents($saleTotalCents, $percent);
        }

        $commission->paid_cents = CommissionPayoutCalculator::netPaidCents($commission);
        $commission->paid_at = $commission->payments()->max('paid_at');
        $commission->status = CommissionPayoutCalculator::statusFor(
            (int) $commission->paid_cents,
            (int) $commission->amount_cents,
        );

        // Perda assumida pela regra antiga: some quando a comissão volta para dentro do teto.
        if ($commission->loss_notified_at !== null && CommissionPayoutCalculator::ceilingExcessCents($commission) === 0) {
            $commission->loss_notified_at = null;
        }

        if ($commission->isDirty()) {
            $commission->save();
        }

        if ($commission->loss_notified_at !== null) {
            return;
        }

        $created = $this->reconcileCeilingDrop($commission);
        $this->reconcileClientDefault($commission, $sale);

        if ($created !== null) {
            $this->notices->commissionRefundDue($commission->fresh(['sale', 'seller']), $created);
        }
    }

    /**
     * Excesso sobre o teto ainda não coberto por devolução pendente nem por perda assumida.
     */
    private function reconcileCeilingDrop(CommercialCommission $commission): ?CommercialCommissionRefund
    {
        $refunds = $commission->refunds()
            ->where('origin', CommercialCommissionRefund::ORIGIN_CEILING_DROP)
            ->orderByDesc('id')
            ->get();

        $pending = (int) $refunds->sum(fn (CommercialCommissionRefund $r) => $r->pendingCents());
        $writtenOff = (int) $refunds->sum('written_off_cents');
        $needed = CommissionPayoutCalculator::ceilingExcessCents($commission) - $pending - $writtenOff;

        if ($needed > 0) {
            return $commission->refunds()->create([
                'origin' => CommercialCommissionRefund::ORIGIN_CEILING_DROP,
                'amount_cents' => $needed,
                'status' => CommercialCommissionRefund::STATUS_PENDING,
            ]);
        }

        if ($needed < 0) {
            $this->cancel($refunds, -$needed, 'O teto voltou a cobrir o valor pago.');
        }

        return null;
    }

    /**
     * Cobrança do adiantado acima do que ainda está adiantado (o cliente pagou): cancela a diferença.
     */
    private function reconcileClientDefault(CommercialCommission $commission, CommercialSale $sale): void
    {
        $refunds = $commission->refunds()
            ->where('origin', CommercialCommissionRefund::ORIGIN_CLIENT_DEFAULT)
            ->orderByDesc('id')
            ->get();

        $pending = (int) $refunds->sum(fn (CommercialCommissionRefund $r) => $r->pendingCents());
        if ($pending < 1) {
            return;
        }

        // O que já virou perda continua contando como coberto: só o pendente é cancelado.
        $covered = $pending + (int) $refunds->sum('written_off_cents');
        $advance = CommissionPayoutCalculator::advanceCents($commission, $sale, $sale->installments()->get());

        if ($covered > $advance) {
            $this->cancel(
                $refunds->where('status', CommercialCommissionRefund::STATUS_PENDING),
                min($pending, $covered - $advance),
                'O cliente pagou: o valor adiantado deixou de ser devido.',
            );
        }
    }

    /**
     * @param  iterable<CommercialCommissionRefund>  $refunds  mais recentes primeiro
     */
    private function cancel(iterable $refunds, int $cents, string $note): void
    {
        foreach ($refunds as $refund) {
            if ($cents < 1) {
                return;
            }

            $part = min($cents, $refund->pendingCents());
            if ($part < 1) {
                continue;
            }

            $refund->entries()->create([
                'type' => CommercialCommissionRefundEntry::TYPE_CANCELLED,
                'amount_cents' => $part,
                'occurred_at' => now(),
                'notes' => $note,
            ]);
            $refund->syncTotals();
            $cents -= $part;
        }
    }
}
