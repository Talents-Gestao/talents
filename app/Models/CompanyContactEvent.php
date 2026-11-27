<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CompanyContactEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Histórico do contato da empresa: só inserção. */
class CompanyContactEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'company_contact_id',
        'type',
        'meta',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => CompanyContactEventType::class,
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new LogicException('O histórico do contato não pode ser alterado.');
        });
        static::deleting(static function (): void {
            throw new LogicException('O histórico do contato não pode ser excluído.');
        });
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CompanyContact::class, 'company_contact_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
