<?php

declare(strict_types=1);

namespace App\Actions\Commercial;

use App\Models\CommercialCommission;
use App\Models\CommercialCommissionPayment;
use App\Models\FinancePayable;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Desfaz o pagamento mais recente de uma comissão (correção de lançamento).
 * Exclusão lógica: guarda deleted_by e deleted_at para auditoria. A conta a pagar
 * baixada gerada pelo pagamento é removida na mesma transação (o saldo volta).
 */
final class DeleteLatestCommissionPayment
{
    public function __construct(
        private readonly RefreshCommissionPayout $refresh,
    ) {}

    public function handle(CommercialCommission $commission, CommercialCommissionPayment $payment, ?User $actor): void
    {
        DB::transaction(function () use ($commission, $payment, $actor): void {
            $locked = CommercialCommission::query()->whereKey($commission->id)->lockForUpdate()->firstOrFail();

            $latestId = (int) $locked->payments()->max('id');
            if ((int) $payment->commission_id !== (int) $locked->id || (int) $payment->id !== $latestId) {
                throw ValidationException::withMessages([
                    'payment' => 'Só é possível excluir o pagamento mais recente desta comissão.',
                ]);
            }

            // Desfaz a saída de caixa junto: a conta a pagar de origem comissão some com o lançamento.
            FinancePayable::query()->where('commission_payment_id', $payment->id)->delete();

            $payment->forceFill(['deleted_by' => $actor?->id])->save();
            $payment->delete();

            $this->refresh->forCommission($locked);
        });
    }
}
