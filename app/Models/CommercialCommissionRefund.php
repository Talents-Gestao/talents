<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Devolução de comissão cobrada da colaboradora.
 *
 * Origem: teto abaixo do pago (parcela ou venda cancelada, criada automaticamente) ou cliente que
 * não pagou (cobrança manual sobre o adiantado). Pendente = valor − devolvido − perda − cancelado.
 */
class CommercialCommissionRefund extends Model
{
    public const ORIGIN_CEILING_DROP = 'ceiling_drop';

    public const ORIGIN_CLIENT_DEFAULT = 'client_default';

    public const STATUS_PENDING = 'pending';

    public const STATUS_RESOLVED = 'resolved';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'returned_cents' => 'integer',
            'written_off_cents' => 'integer',
            'cancelled_cents' => 'integer',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function originLabels(): array
    {
        return [
            self::ORIGIN_CEILING_DROP => 'Teto abaixo do valor pago',
            self::ORIGIN_CLIENT_DEFAULT => 'Cliente não pagou',
        ];
    }

    public function pendingCents(): int
    {
        return max(0, $this->amount_cents - $this->returned_cents - $this->written_off_cents - $this->cancelled_cents);
    }

    /** Recalcula as somas a partir do histórico e o status. */
    public function syncTotals(): void
    {
        $sums = $this->entries()
            ->selectRaw('type, coalesce(sum(amount_cents), 0) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $this->returned_cents = (int) ($sums[CommercialCommissionRefundEntry::TYPE_RETURNED] ?? 0);
        $this->written_off_cents = (int) ($sums[CommercialCommissionRefundEntry::TYPE_LOSS_ASSUMED] ?? 0);
        $this->cancelled_cents = (int) ($sums[CommercialCommissionRefundEntry::TYPE_CANCELLED] ?? 0);
        $this->status = $this->pendingCents() > 0 ? self::STATUS_PENDING : self::STATUS_RESOLVED;
        $this->save();
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(CommercialCommission::class, 'commission_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CommercialCommissionRefundEntry::class, 'refund_id');
    }
}
