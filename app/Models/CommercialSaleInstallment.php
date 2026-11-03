<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialSaleInstallment extends Model
{
    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_PAGO = 'pago';

    public const STATUS_CANCELADO = 'cancelado';

    protected $guarded = ['id'];

    protected $appends = ['is_overdue'];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'amount_cents' => 'integer',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'paid_amount_cents' => 'integer',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(CommercialSale::class, 'sale_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(FinanceBankAccount::class, 'bank_account_id');
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAGO;
    }

    /**
     * @param  CarbonInterface  $paidAt  Ver App\Support\Finance\FinancePaymentDate::toPaidAt().
     */
    public function markPaid(
        CarbonInterface $paidAt,
        ?int $paidAmountCents = null,
        ?int $paymentMethodId = null,
        ?int $bankAccountId = null,
    ): void {
        $this->update([
            'status' => self::STATUS_PAGO,
            'paid_at' => $paidAt,
            'paid_amount_cents' => $paidAmountCents ?? $this->amount_cents,
            'bank_account_id' => $bankAccountId ?? $this->bank_account_id,
        ]);
    }

    protected function isOverdue(): Attribute
    {
        return Attribute::get(function (): bool {
            if ($this->status !== self::STATUS_PENDENTE) {
                return false;
            }

            return $this->due_date !== null && $this->due_date->isPast();
        });
    }
}
