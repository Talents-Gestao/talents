<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FinancePayableStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancePayable extends Model
{
    protected $fillable = [
        'title',
        'supplier_name',
        'amount_cents',
        'due_date',
        'status',
        'payment_method_id',
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
            'status' => FinancePayableStatus::class,
        ];
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(FinancePaymentMethod::class, 'payment_method_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function markPaid(?int $paidAmountCents = null, ?int $paymentMethodId = null): void
    {
        $this->update([
            'status' => FinancePayableStatus::Paid,
            'paid_at' => now(),
            'paid_amount_cents' => $paidAmountCents ?? $this->amount_cents,
            'payment_method_id' => $paymentMethodId ?? $this->payment_method_id,
        ]);
    }
}
