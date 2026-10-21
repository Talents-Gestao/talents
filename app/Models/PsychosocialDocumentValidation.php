<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\PsychosocialDocumentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Registro da validação de uma versão validated. Só inserção.
 *
 * in_system: validação feita por usuário dentro do sistema, com declaração aceita.
 * informed: validação feita fora e informada; sem comprovante a linha não existe.
 */
class PsychosocialDocumentValidation extends Model
{
    public const PATH_IN_SYSTEM = 'in_system';

    public const PATH_INFORMED = 'informed';

    public const PATHS = [self::PATH_IN_SYSTEM, self::PATH_INFORMED];

    private const IN_SYSTEM_REQUIRED = ['user_id', 'validated_at', 'declaration_text'];

    private const INFORMED_REQUIRED = [
        'informed_name',
        'informed_role',
        'informed_date',
        'proof_file_path',
        'proof_file_name',
        'proof_sha256',
    ];

    public $timestamps = false;

    protected $fillable = [
        'version_id',
        'path',
        'user_id',
        'validated_at',
        'declaration_text',
        'declaration_accepted',
        'informed_name',
        'informed_role',
        'informed_registry',
        'informed_date',
        'proof_file_path',
        'proof_file_name',
        'proof_sha256',
        'recorded_by',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'validated_at' => 'datetime',
            'declaration_accepted' => 'boolean',
            'informed_date' => 'date',
            'recorded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PsychosocialDocumentValidation $validation): void {
            if (! in_array($validation->path, self::PATHS, true)) {
                throw new PsychosocialDocumentException("Caminho de validação inválido: «{$validation->path}».");
            }

            $required = $validation->path === self::PATH_IN_SYSTEM ? self::IN_SYSTEM_REQUIRED : self::INFORMED_REQUIRED;
            $missing = array_filter($required, fn (string $field): bool => blank($validation->getAttribute($field)));
            if ($missing !== []) {
                throw new PsychosocialDocumentException('Validação incompleta; faltam: '.implode(', ', $missing).'.');
            }

            if ($validation->path === self::PATH_IN_SYSTEM && $validation->declaration_accepted !== true) {
                throw new PsychosocialDocumentException('A validação no sistema exige a declaração aceita.');
            }

            $version = PsychosocialDocumentVersion::query()->find($validation->version_id);
            if ($version === null || $version->kind !== PsychosocialDocumentVersion::KIND_VALIDATED) {
                throw new PsychosocialDocumentException('A validação pertence a uma versão validated.');
            }

            $validation->recorded_at ??= now();
        });

        static::updating(function (): never {
            throw new LogicException('Validações de documento não podem ser alteradas.');
        });

        static::deleting(function (): never {
            throw new LogicException('Validações de documento não podem ser apagadas.');
        });
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(PsychosocialDocumentVersion::class, 'version_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
