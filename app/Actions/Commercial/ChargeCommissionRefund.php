<?php

declare(strict_types=1);

namespace App\Actions\Commercial;

use App\Actions\Notices\PublishCommercialNotice;
use App\Models\CommercialCommission;
use App\Models\CommercialCommissionRefund;
use App\Models\FinanceAuditLog;
use App\Models\User;
use App\Services\Commercial\CommissionPayoutCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «Cobrar devolução»: o cliente não pagou e a colaboradora deve devolver (parte do) adiantado em aberto.
 * Justificativa obrigatória; avisa a equipe (commission_refund_due).
 */
final class ChargeCommissionRefund
{
    public function __construct(
        private readonly RefreshCommissionPayout $refresh,
        private readonly PublishCommercialNotice $notices,
    ) {}

    public function handle(CommercialCommission $commission, int $amountCents, string $justification, ?User $actor): CommercialCommissionRefund
    {
        $refund = DB::transaction(function () use ($commission, $amountCents, $justification, $actor): CommercialCommissionRefund {
            $locked = CommercialCommission::query()->whereKey($commission->id)->lockForUpdate()->firstOrFail();
            $this->refresh->forCommission($locked);
            $locked->refresh();

            $sale = $locked->sale()->firstOrFail();
            $advance = CommissionPayoutCalculator::advanceCents($locked, $sale, $sale->installments()->get());
            $chargeable = CommissionPayoutCalculator::chargeableAdvanceCents(
                $advance,
                $locked->refunds()->where('origin', CommercialCommissionRefund::ORIGIN_CLIENT_DEFAULT)->get(),
            );

            if ($chargeable < 1) {
                throw ValidationException::withMessages([
                    'amount' => 'Não há valor adiantado em aberto para cobrar nesta comissão.',
                ]);
            }

            if ($amountCents < 1 || $amountCents > $chargeable) {
                throw ValidationException::withMessages([
                    'amount' => 'Informe um valor entre R$ 0,01 e o adiantado em aberto: R$ '
                        .number_format($chargeable / 100, 2, ',', '.').'.',
                ]);
            }

            $refund = $locked->refunds()->create([
                'origin' => CommercialCommissionRefund::ORIGIN_CLIENT_DEFAULT,
                'amount_cents' => $amountCents,
                'status' => CommercialCommissionRefund::STATUS_PENDING,
                'justification' => trim($justification),
                'requested_by' => $actor?->id,
            ]);

            FinanceAuditLog::query()->create([
                'auditable_type' => $refund->getMorphClass(),
                'auditable_id' => $refund->id,
                'field' => FinanceAuditLog::FIELD_COMMISSION_REFUND_CHARGED,
                'old_value' => (string) $chargeable,
                'new_value' => (string) $amountCents,
                'user_id' => $actor?->id,
                'reason' => mb_substr(trim($justification), 0, 500),
            ]);

            return $refund;
        });

        $this->notices->commissionRefundDue($commission->fresh(['sale', 'seller']), $refund, $actor);

        return $refund;
    }
}
