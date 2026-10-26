<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Mudança da opção «Exigir revisão PASSEG» de uma campanha: ator, data, valor anterior e novo. Só inserção.
 */
class PsychosocialPassegOptionChange extends Model
{
    public $timestamps = false;

    protected $fillable = ['survey_id', 'user_id', 'previous_value', 'new_value', 'changed_at'];

    protected function casts(): array
    {
        return [
            'previous_value' => 'boolean',
            'new_value' => 'boolean',
            'changed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('O registro de mudança da opção não pode ser alterado.');
        });

        static::deleting(function (): never {
            throw new LogicException('O registro de mudança da opção não pode ser apagado.');
        });
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
