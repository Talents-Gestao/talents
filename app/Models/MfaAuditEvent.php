<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Auditoria do MFA: ator, alvo, ação e data. Só inserção.
 */
class MfaAuditEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['actor_id', 'target_id', 'action', 'ip'];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('A auditoria do MFA não pode ser alterada.');
        });

        static::deleting(function (): never {
            throw new LogicException('A auditoria do MFA não pode ser apagada.');
        });
    }
}
