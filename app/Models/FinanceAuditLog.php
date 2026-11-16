<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Trilha de alterações financeiras: correções depois da baixa (ex.: data de pagamento),
 * pagamentos de comissão (com adiantamento) e devoluções.
 */
class FinanceAuditLog extends Model
{
    public const FIELD_PAID_AT = 'paid_at';

    /** Pagamento de comissão: old_value = liberável no momento, new_value = valor pago (centavos). */
    public const FIELD_COMMISSION_PAYMENT = 'commission_payment';

    /** Pagamento de comissão acima do liberável (adiantado antes do recebimento). */
    public const FIELD_COMMISSION_ADVANCE = 'commission_payment_advance';

    /** Devolução: cobrada, registrada (valor devolvido) ou assumida como perda. new_value em centavos. */
    public const FIELD_COMMISSION_REFUND_CHARGED = 'commission_refund_charged';

    public const FIELD_COMMISSION_REFUND_RETURNED = 'commission_refund_returned';

    public const FIELD_COMMISSION_REFUND_LOSS = 'commission_refund_loss';

    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'field',
        'old_value',
        'new_value',
        'user_id',
        'reason',
    ];

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
