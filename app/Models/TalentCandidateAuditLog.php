<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TalentCandidateAuditAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Auditoria do cadastro de candidatos: só inserção, nunca guarda conteúdo de arquivo. */
class TalentCandidateAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'talent_candidate_id',
        'user_id',
        'action',
        'meta',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'action' => TalentCandidateAuditAction::class,
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new LogicException('A auditoria do candidato não pode ser alterada.');
        });
        static::deleting(static function (): void {
            throw new LogicException('A auditoria do candidato não pode ser excluída.');
        });
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(TalentCandidate::class, 'talent_candidate_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
