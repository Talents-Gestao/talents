<?php

declare(strict_types=1);

namespace App\Actions\Commercial;

use App\Actions\Notices\PublishCommercialNotice;
use App\Models\CommercialCommission;
use App\Models\CommercialCommissionRefund;
use App\Models\CommercialCommissionRefundEntry;
use App\Models\FinanceAuditLog;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tratamento de uma devolução pendente, com histórico e autoria:
 *
 * - returned(): a colaboradora devolveu (total ou parcial). Entra como movimento na conta bancária
 *   escolhida (FinanceBankAccountBalance) e não entra em FinanceCashflowMetrics. Reduz o pago.
 * - assumeLoss(): a empresa assume o valor como perda (justificativa obrigatória; aviso
 *   commission_loss_assumed).
 */
final class ResolveCommissionRefund
{
    public function __construct(
        private readonly RefreshCommissionPayout $refresh,
        private readonly PublishCommercialNotice $notices,
    ) {}

    public function returned(
        CommercialCommissionRefund $refund,
        int $amountCents,
        CarbonInterface $returnedAt,
        int $bankAccountId,
        ?string $notes,
        ?User $actor,
    ): CommercialCommissionRefundEntry {
        return DB::transaction(function () use ($refund, $amountCents, $returnedAt, $bankAccountId, $notes, $actor): CommercialCommissionRefundEntry {
            [$locked, $commission] = $this->lockPending($refund, $amountCents);

            $entry = $locked->entries()->create([
                'type' => CommercialCommissionRefundEntry::TYPE_RETURNED,
                'amount_cents' => $amountCents,
                'occurred_at' => $returnedAt,
                'bank_account_id' => $bankAccountId,
                'notes' => filled($notes) ? trim((string) $notes) : null,
                'user_id' => $actor?->id,
            ]);
            $locked->syncTotals();

            $this->audit($locked, FinanceAuditLog::FIELD_COMMISSION_REFUND_RETURNED, $amountCents, $actor, $notes);
            $this->refresh->forCommission($commission);

            return $entry;
        });
    }

    public function assumeLoss(
        CommercialCommissionRefund $refund,
        int $amountCents,
        string $justification,
        ?User $actor,
    ): CommercialCommissionRefundEntry {
        $justification = trim($justification);

        $entry = DB::transaction(function () use ($refund, $amountCents, $justification, $actor): CommercialCommissionRefundEntry {
            [$locked, $commission] = $this->lockPending($refund, $amountCents);

            $entry = $locked->entries()->create([
                'type' => CommercialCommissionRefundEntry::TYPE_LOSS_ASSUMED,
                'amount_cents' => $amountCents,
                'occurred_at' => now(),
                'notes' => $justification,
                'user_id' => $actor?->id,
            ]);
            $locked->syncTotals();

            $this->audit($locked, FinanceAuditLog::FIELD_COMMISSION_REFUND_LOSS, $amountCents, $actor, $justification);
            $this->refresh->forCommission($commission);

            return $entry;
        });

        $this->notices->commissionLossAssumed(
            $refund->commission()->with(['sale', 'seller'])->firstOrFail(),
            $amountCents,
            $justification,
            $actor,
        );

        return $entry;
    }

    /**
     * @return array{0: CommercialCommissionRefund, 1: CommercialCommission}
     */
    private function lockPending(CommercialCommissionRefund $refund, int $amountCents): array
    {
        $commission = CommercialCommission::query()->whereKey($refund->commission_id)->lockForUpdate()->firstOrFail();
        $locked = CommercialCommissionRefund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
        $pending = $locked->pendingCents();

        if ($pending < 1) {
            throw ValidationException::withMessages([
                'amount' => 'Esta devolução não tem valor pendente.',
            ]);
        }

        if ($amountCents < 1 || $amountCents > $pending) {
            throw ValidationException::withMessages([
                'amount' => 'Informe um valor entre R$ 0,01 e o pendente da devolução: R$ '
                    .number_format($pending / 100, 2, ',', '.').'.',
            ]);
        }

        return [$locked, $commission];
    }

    private function audit(CommercialCommissionRefund $refund, string $field, int $amountCents, ?User $actor, ?string $reason): void
    {
        FinanceAuditLog::query()->create([
            'auditable_type' => $refund->getMorphClass(),
            'auditable_id' => $refund->id,
            'field' => $field,
            'old_value' => (string) ($refund->pendingCents() + $amountCents),
            'new_value' => (string) $amountCents,
            'user_id' => $actor?->id,
            'reason' => filled($reason) ? mb_substr(trim((string) $reason), 0, 500) : null,
        ]);
    }
}
