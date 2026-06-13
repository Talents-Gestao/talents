<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialCommission extends Model
{
    public const STATUS_A_PAGAR = 'a_pagar';

    public const STATUS_PAGA = 'paga';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'base_cents' => 'integer',
            'percent' => 'float',
            'amount_cents' => 'integer',
            'paid_at' => 'datetime',
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
}
