<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Histórico de alterações de uma medida. Só inserção.
 */
class PsychosocialMeasureEvent extends Model
{
    public const UPDATED_AT = null;

    public const FIELD_CREATED = 'created';

    protected $fillable = [
        'measure_id',
        'user_id',
        'field',
        'old_value',
        'new_value',
        'reason',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Eventos de medida não podem ser alterados.');
        });

        static::deleting(function (): never {
            throw new LogicException('Eventos de medida não podem ser apagados.');
        });
    }

    public function measure(): BelongsTo
    {
        return $this->belongsTo(PsychosocialMeasure::class, 'measure_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
