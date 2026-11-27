<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Resposta da pesquisa pós-contratação (uma por convite). O comentário é interno da Talents:
 * nunca vai ao gestor nem ao portal da empresa.
 */
class HiringFollowupResponse extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'invitation_id',
        'rating',
        'comment',
        'support_requested',
        'support_note',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'support_requested' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(HiringFollowupInvitation::class, 'invitation_id');
    }
}
