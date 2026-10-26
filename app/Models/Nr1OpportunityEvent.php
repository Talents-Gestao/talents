<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Histórico da oportunidade (criação, assumir, mudança de estado, próxima ação, recusa). Só inserção.
 */
class Nr1OpportunityEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['opportunity_id', 'user_id', 'action', 'from_status', 'to_status', 'body'];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('O histórico da oportunidade não pode ser alterado.');
        });

        static::deleting(function (): never {
            throw new LogicException('O histórico da oportunidade não pode ser apagado.');
        });
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Nr1Opportunity::class, 'opportunity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
