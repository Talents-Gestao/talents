<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\PsychosocialDocumentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Comentário ou aprovação do revisor externo sobre uma versão compartilhada. Só inserção.
 * Aprovar não altera a versão (ela é imutável): o aceite fica registrado aqui.
 */
class DocumentShareReview extends Model
{
    public const UPDATED_AT = null;

    public const KIND_COMMENT = 'comment';

    public const KIND_APPROVAL = 'approval';

    public const KINDS = [self::KIND_COMMENT, self::KIND_APPROVAL];

    protected $fillable = ['document_share_id', 'user_id', 'kind', 'body'];

    protected static function booted(): void
    {
        static::creating(function (DocumentShareReview $review): void {
            if (! in_array($review->kind, self::KINDS, true)) {
                throw new PsychosocialDocumentException("Tipo de revisão inválido: «{$review->kind}».");
            }

            if ($review->kind === self::KIND_COMMENT && trim((string) $review->body) === '') {
                throw new PsychosocialDocumentException('O comentário não pode ficar vazio.');
            }
        });

        static::updating(function (): never {
            throw new LogicException('Revisões não podem ser alteradas.');
        });

        static::deleting(function (): never {
            throw new LogicException('Revisões não podem ser apagadas.');
        });
    }

    public function share(): BelongsTo
    {
        return $this->belongsTo(DocumentShare::class, 'document_share_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
