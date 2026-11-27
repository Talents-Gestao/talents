<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmploymentCycleEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Histórico e registro de acompanhamento do ciclo: só inserção. */
class EmploymentCycleEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'employment_cycle_id',
        'type',
        'note',
        'meta',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => EmploymentCycleEventType::class,
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new LogicException('O histórico do ciclo não pode ser alterado.');
        });
        static::deleting(static function (): void {
            throw new LogicException('O histórico do ciclo não pode ser excluído.');
        });
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(EmploymentCycle::class, 'employment_cycle_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
