<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Análise interna da Talents antes de emitir o relatório (Caderno §9). Visível só à consultoria; não aparece para a
 * empresa, a PASSEG nem o Comercial e não gera contratação. Só inserção: cada salvamento é um registro do histórico.
 */
class PsychosocialInternalReview extends Model
{
    public const DECISION_DOCUMENTAL = 'documental';

    public const DECISION_COMPLEMENTARY = 'entrevistas';

    public const DECISION_SUFFICIENT = 'suficiente';

    public const DECISION_LABELS = [
        self::DECISION_DOCUMENTAL => 'Solicitar informação ou documento',
        self::DECISION_COMPLEMENTARY => 'Sugerir avaliação complementar',
        self::DECISION_SUFFICIENT => 'Informações suficientes para esta etapa',
    ];

    public const UPDATED_AT = null;

    protected $fillable = ['survey_id', 'user_id', 'decision', 'note', 'checked_items'];

    protected function casts(): array
    {
        return ['checked_items' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (PsychosocialInternalReview $review): void {
            if (! array_key_exists((string) $review->decision, self::DECISION_LABELS)) {
                throw new \InvalidArgumentException('Encaminhamento inválido.');
            }
        });

        static::updating(function (): never {
            throw new LogicException('A análise interna registrada não pode ser alterada: salve uma nova.');
        });

        static::deleting(function (): never {
            throw new LogicException('A análise interna registrada não pode ser apagada.');
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
