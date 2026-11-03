<?php

declare(strict_types=1);

namespace App\Actions\Commercial;

use App\Actions\Notices\PublishCommercialNotice;
use App\Models\CommercialCommission;
use App\Models\CommercialCommissionPayment;
use App\Models\CommercialSale;
use App\Models\User;
use App\Services\Commercial\CommissionPayoutCalculator;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra um repasse de comissão limitado ao valor liberável no momento
 * (proporcional ao recebido; total quando a venda está quitada).
 */
final class RegisterCommissionPayment
{
    public function __construct(
        private readonly RefreshCommissionPayout $refresh,
        private readonly PublishCommercialNotice $notices,
    ) {}

    public function handle(
        CommercialCommission $commission,
        int $amountCents,
        CarbonInterface $paidAt,
        ?string $notes,
        ?User $actor,
    ): CommercialCommissionPayment {
        $payment = DB::transaction(function () use ($commission, $amountCents, $paidAt, $notes, $actor): CommercialCommissionPayment {
            $locked = CommercialCommission::query()->whereKey($commission->id)->lockForUpdate()->firstOrFail();
            $this->refresh->forCommission($locked);
            $locked->refresh();

            /** @var CommercialSale $sale */
            $sale = $locked->sale()->firstOrFail();
            $releasable = CommissionPayoutCalculator::releasableCents($locked, $sale, $sale->installments()->get());

            if ($releasable < 1) {
                throw ValidationException::withMessages([
                    'amount' => 'Não há valor liberável para esta comissão agora: o pagamento acompanha o que já foi recebido da venda.',
                ]);
            }

            if ($amountCents < 1) {
                throw ValidationException::withMessages([
                    'amount' => 'Informe um valor maior que zero.',
                ]);
            }

            if ($amountCents > $releasable) {
                throw ValidationException::withMessages([
                    'amount' => 'O valor não pode passar do liberável agora: R$ '
                        .number_format($releasable / 100, 2, ',', '.').'.',
                ]);
            }

            $payment = CommercialCommissionPayment::query()->create([
                'commission_id' => $locked->id,
                'amount_cents' => $amountCents,
                'paid_at' => $paidAt,
                'paid_by' => $actor?->id,
                'notes' => filled($notes) ? trim((string) $notes) : null,
            ]);

            $this->refresh->forCommission($locked);

            return $payment;
        });

        $this->notices->commissionPaid(
            $commission->fresh(['sale', 'seller']),
            $payment,
            $actor,
        );

        return $payment;
    }
}
