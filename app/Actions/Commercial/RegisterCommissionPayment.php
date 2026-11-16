<?php

declare(strict_types=1);

namespace App\Actions\Commercial;

use App\Actions\Notices\PublishCommercialNotice;
use App\Enums\FinancePayableStatus;
use App\Models\CommercialCommission;
use App\Models\CommercialCommissionPayment;
use App\Models\CommercialSale;
use App\Models\FinanceAuditLog;
use App\Models\FinancePayable;
use App\Models\User;
use App\Services\Commercial\CommissionPayoutCalculator;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra um repasse de comissão e, na mesma transação, a conta a pagar baixada que tira o valor
 * da conta bancária escolhida, e a linha de auditoria (autor, data, valor e se foi adiantado).
 *
 * Limite: sem a permissão de adiantar, o liberável proporcional ao recebido; com ela, o saldo da
 * comissão (teto − pago), com confirmação explícita da parte acima do liberável. Nunca acima do teto.
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
        int $bankAccountId,
        ?string $notes,
        ?User $actor,
        bool $canAdvance = false,
        bool $advanceConfirmed = false,
    ): CommercialCommissionPayment {
        $payment = DB::transaction(function () use ($commission, $amountCents, $paidAt, $bankAccountId, $notes, $actor, $canAdvance, $advanceConfirmed): CommercialCommissionPayment {
            $locked = CommercialCommission::query()->whereKey($commission->id)->lockForUpdate()->firstOrFail();
            $this->refresh->forCommission($locked);
            $locked->refresh();

            /** @var CommercialSale $sale */
            $sale = $locked->sale()->firstOrFail();
            $releasable = CommissionPayoutCalculator::releasableCents($locked, $sale, $sale->installments()->get());
            $limit = CommissionPayoutCalculator::paymentLimitCents($locked);

            if ($amountCents < 1) {
                throw ValidationException::withMessages([
                    'amount' => 'Informe um valor maior que zero.',
                ]);
            }

            if (! $canAdvance && $amountCents > $releasable) {
                throw ValidationException::withMessages([
                    'amount' => self::aboveReleasableMessage($releasable),
                ]);
            }

            if ($amountCents > $limit) {
                throw ValidationException::withMessages([
                    'amount' => self::aboveCeilingMessage($limit),
                ]);
            }

            $advance = max(0, $amountCents - $releasable);
            if ($advance > 0 && ! $advanceConfirmed) {
                throw ValidationException::withMessages([
                    'confirm_advance' => self::advanceWarning($advance).' Confirme o adiantamento para continuar.',
                ]);
            }

            $notes = filled($notes) ? trim((string) $notes) : null;

            $payment = CommercialCommissionPayment::query()->create([
                'commission_id' => $locked->id,
                'amount_cents' => $amountCents,
                'advance_cents' => $advance,
                'paid_at' => $paidAt,
                'paid_by' => $actor?->id,
                'notes' => $notes,
            ]);

            // Saída de caixa: conta a pagar já baixada, que o saldo bancário já considera.
            $sellerName = $locked->seller()->value('name');
            (new FinancePayable)->forceFill([
                'title' => 'Comissão · '.($sellerName ?? 'sem beneficiário').' · '.$sale->code,
                'supplier_name' => $sellerName,
                'amount_cents' => $amountCents,
                'due_date' => $paidAt->toDateString(),
                'status' => FinancePayableStatus::Paid,
                'paid_at' => $paidAt,
                'paid_amount_cents' => $amountCents,
                'bank_account_id' => $bankAccountId,
                'notes' => $notes,
                'created_by' => $actor?->id,
                'is_recurring' => false,
                'commission_payment_id' => $payment->id,
            ])->save();

            FinanceAuditLog::query()->create([
                'auditable_type' => $payment->getMorphClass(),
                'auditable_id' => $payment->id,
                'field' => $advance > 0 ? FinanceAuditLog::FIELD_COMMISSION_ADVANCE : FinanceAuditLog::FIELD_COMMISSION_PAYMENT,
                'old_value' => (string) $releasable,
                'new_value' => (string) $amountCents,
                'user_id' => $actor?->id,
                'reason' => $notes,
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

    /** Sem a permissão de adiantar: o pagamento acompanha o recebido. */
    public static function aboveReleasableMessage(int $releasableCents): string
    {
        return 'O valor liberável agora é '.self::money($releasableCents)
            .'; o restante depende do recebimento da venda.';
    }

    public static function aboveCeilingMessage(int $limitCents): string
    {
        return 'O valor não pode passar do saldo da comissão: '.self::money($limitCents).'.';
    }

    /** Texto da confirmação do adiantamento (o mesmo da tela). */
    public static function advanceWarning(int $advanceCents): string
    {
        return 'Você está pagando '.self::money($advanceCents).' acima do que o cliente já pagou. '
            .'Se o cliente não pagar, esse valor deverá ser devolvido pela colaboradora.';
    }

    private static function money(int $cents): string
    {
        return 'R$ '.number_format(max(0, $cents) / 100, 2, ',', '.');
    }
}
