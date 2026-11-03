<?php

declare(strict_types=1);

namespace App\Actions\Commercial;

use App\Models\CommercialCommission;
use App\Models\CommercialSale;
use App\Services\Commercial\CommissionPayoutCalculator;

/**
 * Recalcula os campos derivados das comissões de uma venda:
 * teto (base/amount) a partir das parcelas não canceladas, paid_cents, status e paid_at.
 *
 * Teto menor que o já pago não estorna nada: a tela sinaliza «pago acima do teto».
 * Comissão sem percentual (legado) mantém o valor gravado.
 */
final class RefreshCommissionPayout
{
    public function forSale(CommercialSale $sale): void
    {
        $installments = $sale->installments()->get();
        $totalCents = CommissionPayoutCalculator::saleTotalCents($sale, $installments);

        $sale->commissions()->get()->each(
            fn (CommercialCommission $commission) => $this->apply($commission, $totalCents),
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
            CommissionPayoutCalculator::saleTotalCents($sale, $sale->installments()->get()),
        );
    }

    private function apply(CommercialCommission $commission, int $saleTotalCents): void
    {
        $percent = (float) $commission->percent;

        if ($percent > 0) {
            $commission->base_cents = $saleTotalCents;
            $commission->amount_cents = CommissionPayoutCalculator::ceilingCents($saleTotalCents, $percent);
        }

        $commission->paid_cents = (int) $commission->payments()->sum('amount_cents');
        $commission->paid_at = $commission->payments()->max('paid_at');
        $commission->status = CommissionPayoutCalculator::statusFor(
            (int) $commission->paid_cents,
            (int) $commission->amount_cents,
        );

        if ($commission->isDirty()) {
            $commission->save();
        }
    }
}
