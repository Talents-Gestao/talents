<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Histórico da devolução: valor devolvido (entra na conta bancária), perda assumida pela empresa
 * ou cancelamento automático (o cliente pagou ou o teto voltou a cobrir o pago).
 */
class CommercialCommissionRefundEntry extends Model
{
    public const TYPE_RETURNED = 'returned';

    public const TYPE_LOSS_ASSUMED = 'loss_assumed';

    public const TYPE_CANCELLED = 'cancelled';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_RETURNED => 'Devolução registrada',
            self::TYPE_LOSS_ASSUMED => 'Perda assumida',
            self::TYPE_CANCELLED => 'Cancelada automaticamente',
        ];
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(CommercialCommissionRefund::class, 'refund_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(FinanceBankAccount::class, 'bank_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
