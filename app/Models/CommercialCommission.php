<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Status derivado dos pagamentos (ver CommissionPayoutCalculator::statusFor):
 * a_pagar (nada pago), parcial (0 < pago < total), paga (pago ≥ total).
 */
class CommercialCommission extends Model
{
    public const STATUS_A_PAGAR = 'a_pagar';

    public const STATUS_PARCIAL = 'parcial';

    public const STATUS_PAGA = 'paga';

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_A_PAGAR => 'A pagar',
            self::STATUS_PARCIAL => 'Parcial',
            self::STATUS_PAGA => 'Paga',
        ];
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'base_cents' => 'integer',
            'percent' => 'float',
            'amount_cents' => 'integer',
            'paid_cents' => 'integer',
            'paid_at' => 'datetime',
            'loss_notified_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(CommercialSale::class, 'sale_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * Pagamentos ativos (sem os excluídos). Sem orderBy aqui: a relação também é usada
     * em sum/max, e o Postgres recusa ORDER BY em agregado. Ordene ao carregar.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(CommercialCommissionPayment::class, 'commission_id');
    }
}
