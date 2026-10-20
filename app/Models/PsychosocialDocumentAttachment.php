<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\PsychosocialDocumentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Anexo de uma versão de documento (AEP, observação, inventário). Só inserção.
 */
class PsychosocialDocumentAttachment extends Model
{
    public const UPDATED_AT = null;

    public const KINDS = ['aep', 'observation', 'inventory', 'other'];

    protected $fillable = [
        'version_id',
        'kind',
        'file_path',
        'file_name',
        'sha256',
        'uploaded_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (PsychosocialDocumentAttachment $attachment): void {
            if (! in_array($attachment->kind, self::KINDS, true)) {
                throw new PsychosocialDocumentException("Tipo de anexo inválido: «{$attachment->kind}».");
            }
        });

        static::updating(function (): never {
            throw new LogicException('Anexos de documento não podem ser alterados.');
        });

        static::deleting(function (): never {
            throw new LogicException('Anexos de documento não podem ser apagados.');
        });
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(PsychosocialDocumentVersion::class, 'version_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
