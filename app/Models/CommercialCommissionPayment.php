<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Repasse (total ou parcial) de uma comissão ao beneficiário.
 *
 * Exclusão é lógica: deleted_at + deleted_by registram quem desfez o lançamento.
 */
class CommercialCommissionPayment extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(CommercialCommission::class, 'commission_id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Conta a pagar baixada que move o saldo bancário. Nula nos pagamentos legados
     * (anteriores à Fase 2), que não geram movimento retroativo.
     */
    public function payable(): HasOne
    {
        return $this->hasOne(FinancePayable::class, 'commission_payment_id');
    }
}
