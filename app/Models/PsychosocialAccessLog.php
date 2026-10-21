<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\PsychosocialDocumentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Log de acesso às saídas novas. Só inserção; survey_id e document_version_id não têm FK
 * para que o registro sobreviva à limpeza da campanha.
 */
class PsychosocialAccessLog extends Model
{
    public const UPDATED_AT = null;

    public const RESOURCES = ['dashboard', 'document_download', 'export_csv', 'export_json'];

    protected $fillable = [
        'user_id',
        'company_id',
        'survey_id',
        'resource',
        'document_version_id',
        'ip',
    ];

    protected static function booted(): void
    {
        static::creating(function (PsychosocialAccessLog $log): void {
            if (! in_array($log->resource, self::RESOURCES, true)) {
                throw new PsychosocialDocumentException("Recurso de acesso inválido: «{$log->resource}».");
            }
        });

        static::updating(function (): never {
            throw new LogicException('Logs de acesso não podem ser alterados.');
        });

        static::deleting(function (): never {
            throw new LogicException('Logs de acesso não podem ser apagados.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
