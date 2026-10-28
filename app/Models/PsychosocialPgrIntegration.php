<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\PsychosocialDocumentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * "Integração ao PGR confirmada": campo separado de "Aprovado pela PASSEG" (que é só revisão documental).
 * Só existe com o arquivo de evidência (documento de SST), com hash. Só inserção.
 */
class PsychosocialPgrIntegration extends Model
{
    public $timestamps = false;

    protected $fillable = ['round_id', 'user_id', 'note', 'evidence_path', 'evidence_name', 'evidence_sha256', 'confirmed_at'];

    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (PsychosocialPgrIntegration $integration): void {
            if (blank($integration->evidence_path) || blank($integration->evidence_sha256)) {
                throw new PsychosocialDocumentException('A integração ao PGR só é confirmada com a evidência (documento de SST).');
            }
        });

        static::updating(function (): never {
            throw new LogicException('A confirmação da integração ao PGR não pode ser alterada.');
        });

        static::deleting(function (): never {
            throw new LogicException('A confirmação da integração ao PGR não pode ser apagada.');
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
