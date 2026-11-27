<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HiringApplicationEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Histórico da candidatura: só inserção. */
class HiringApplicationEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'hiring_application_id',
        'type',
        'from_stage_id',
        'to_stage_id',
        'note',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => HiringApplicationEventType::class,
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new LogicException('O histórico da candidatura não pode ser alterado.');
        });
        static::deleting(static function (): void {
            throw new LogicException('O histórico da candidatura não pode ser excluído.');
        });
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(HiringApplication::class, 'hiring_application_id');
    }

    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(HiringVacancyStage::class, 'from_stage_id');
    }

    public function toStage(): BelongsTo
    {
        return $this->belongsTo(HiringVacancyStage::class, 'to_stage_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
