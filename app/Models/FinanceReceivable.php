<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FinanceReceivableStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceReceivable extends Model
{
    protected $fillable = [
        'title',
        'payer_name',
        'amount_cents',
        'due_date',
        'status',
        'payment_method_id',
        'bank_account_id',
        'paid_at',
        'paid_amount_cents',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'paid_amount_cents' => 'integer',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'status' => FinanceReceivableStatus::class,
        ];
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(FinancePaymentMethod::class, 'payment_method_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(FinanceBankAccount::class, 'bank_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function markPaid(?int $paidAmountCents = null, ?int $paymentMethodId = null, ?int $bankAccountId = null): void
    {
        $this->update([
            'status' => FinanceReceivableStatus::Paid,
            'paid_at' => now(),
            'paid_amount_cents' => $paidAmountCents ?? $this->amount_cents,
            'payment_method_id' => $paymentMethodId ?? $this->payment_method_id,
            'bank_account_id' => $bankAccountId ?? $this->bank_account_id,
        ]);
    }
}
