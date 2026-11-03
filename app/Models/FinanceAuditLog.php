<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Trilha de alterações financeiras feitas depois da baixa (ex.: data de pagamento).
 */
class FinanceAuditLog extends Model
{
    public const FIELD_PAID_AT = 'paid_at';

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
