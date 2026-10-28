<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Ciência da empresa (Caderno §13): uma por publicação, para os dois documentos juntos, com usuário, data
 * e o protocolo de encaminhamento. É registro de recebimento, não selo técnico sobre o conteúdo. Só inserção.
 */
class PsychosocialReviewAcknowledgement extends Model
{
    public $timestamps = false;

    protected $fillable = ['round_id', 'user_id', 'protocol', 'acknowledged_at'];

    protected function casts(): array
    {
        return ['acknowledged_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('A ciência registrada não pode ser alterada.');
        });

        static::deleting(function (): never {
            throw new LogicException('A ciência registrada não pode ser apagada.');
        });
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(PsychosocialReviewRound::class, 'round_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
