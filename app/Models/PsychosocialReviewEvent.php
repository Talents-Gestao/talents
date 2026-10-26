<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\PsychosocialDocumentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Histórico da rodada: quem fez o quê, quando e com qual justificativa. Só inserção.
 */
class PsychosocialReviewEvent extends Model
{
    public const UPDATED_AT = null;

    public const ACTION_LABELS = [
        'created' => 'Revisão iniciada',
        'regenerated' => 'Documentos gerados',
        'sent' => 'Enviado à revisão da PASSEG',
        'change_requested' => 'Alteração solicitada pela PASSEG',
        'approved' => 'Aprovado pela PASSEG',
        'published' => 'Publicado para a empresa',
        'acknowledged' => 'Ciência da empresa registrada',
        'pgr_integration_confirmed' => 'Integração ao PGR confirmada',
    ];

    protected $fillable = ['round_id', 'user_id', 'action', 'from_status', 'to_status', 'body'];

    protected static function booted(): void
    {
        static::creating(function (PsychosocialReviewEvent $event): void {
            if (! array_key_exists($event->action, self::ACTION_LABELS)) {
                throw new PsychosocialDocumentException("Ação de revisão inválida: «{$event->action}».");
            }
        });

        static::updating(function (): never {
            throw new LogicException('Eventos de revisão não podem ser alterados.');
        });

        static::deleting(function (): never {
            throw new LogicException('Eventos de revisão não podem ser apagados.');
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
