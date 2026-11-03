<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Models\CommercialSaleInstallment;
use App\Models\FinanceAuditLog;
use App\Models\FinancePayable;
use App\Models\FinanceReceivable;
use App\Models\User;
use App\Support\Finance\FinancePaymentDate;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Corrige a data de pagamento de um lançamento já baixado.
 *
 * Mexe só em paid_at: valor pago, conta bancária, status e comprovante ficam
 * como estão (o saldo bancário não muda) e nenhum aviso é disparado. Toda
 * alteração efetiva fica em finance_audit_logs.
 */
final class ChangeFinancePaymentDate
{
    /**
     * @return bool true se a data mudou (e foi auditada)
     */
    public function handle(
        CommercialSaleInstallment|FinanceReceivable|FinancePayable $entry,
        CarbonInterface $paidAt,
        ?User $actor = null,
        ?string $reason = null,
    ): bool {
        if (! $entry->isPaid() || FinancePaymentDate::sameDay($entry->paid_at, $paidAt)) {
            return false;
        }

        // Saída gerada por pagamento de comissão: data só muda refazendo o pagamento em Comissões.
        if ($entry instanceof FinancePayable && $entry->isCommissionOrigin()) {
            throw ValidationException::withMessages([
                'paid_at' => 'A data deste lançamento vem do pagamento de comissão. '
                    .'Para corrigir, exclua o último pagamento da comissão em Financeiro → Comissões.',
            ]);
        }

        $reason = filled($reason) ? trim((string) $reason) : null;

        DB::transaction(function () use ($entry, $paidAt, $actor, $reason): void {
            $old = $entry->paid_at;

            $entry->forceFill(['paid_at' => $paidAt])->save();

            FinanceAuditLog::query()->create([
                'auditable_type' => $entry->getMorphClass(),
                'auditable_id' => $entry->getKey(),
                'field' => FinanceAuditLog::FIELD_PAID_AT,
                'old_value' => $old?->toDateTimeString(),
                'new_value' => $entry->paid_at?->toDateTimeString(),
                'user_id' => $actor?->id,
                'reason' => $reason,
            ]);
        });

        return true;
    }
}
