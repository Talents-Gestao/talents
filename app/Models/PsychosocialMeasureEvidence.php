<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\PsychosocialMeasureException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Evidência de execução de uma medida: arquivo (com hash) ou registro em texto. Só inserção.
 */
class PsychosocialMeasureEvidence extends Model
{
    public const UPDATED_AT = null;

    // "evidence" é incontável no pluralizador do Laravel; a tabela é a do plural explícito.
    protected $table = 'psychosocial_measure_evidences';

    public const KIND_FILE = 'file';

    public const KIND_TEXT = 'text';

    public const KINDS = [self::KIND_FILE, self::KIND_TEXT];

    protected $fillable = [
        'measure_id',
        'user_id',
        'kind',
        'description',
        'file_path',
        'file_name',
        'file_sha256',
    ];

    protected static function booted(): void
    {
        static::creating(function (PsychosocialMeasureEvidence $evidence): void {
            if (! in_array($evidence->kind, self::KINDS, true)) {
                throw new PsychosocialMeasureException("Tipo de evidência inválido: «{$evidence->kind}».");
            }

            if (blank($evidence->description)) {
                throw new PsychosocialMeasureException('A evidência precisa de uma descrição.');
            }

            if ($evidence->kind === self::KIND_FILE && (blank($evidence->file_path) || blank($evidence->file_sha256))) {
                throw new PsychosocialMeasureException('Evidência em arquivo exige o arquivo e o hash.');
            }
        });

        static::updating(function (): never {
            throw new LogicException('Evidências não podem ser alteradas.');
        });

        static::deleting(function (): never {
            throw new LogicException('Evidências não podem ser apagadas.');
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
