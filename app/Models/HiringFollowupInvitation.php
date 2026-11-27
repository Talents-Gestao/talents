<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FollowupAudience;
use App\Enums\FollowupInvitationState;
use App\Support\Hiring\FollowupMilestoneStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Convite individual (marco + destino + destinatário registrado no momento). Link público por token,
 * válido por 30 dias. Gerado, copiado, aceito pelo servidor de e-mail, respondido, expirado e revogado
 * são estados separados.
 */
class HiringFollowupInvitation extends Model
{
    public const VALID_DAYS = 30;

    protected $fillable = [
        'milestone_id',
        'audience',
        'recipient_name',
        'recipient_email',
        'recipient_phone',
        'token',
        'expires_at',
        'revoked_at',
        'revoke_reason',
        'copied_at',
        'emailed_at',
        'email_failure_reason',
        'answered_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'audience' => FollowupAudience::class,
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'copied_at' => 'datetime',
            'emailed_at' => 'datetime',
            'answered_at' => 'datetime',
        ];
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(HiringFollowupMilestone::class, 'milestone_id');
    }

    public function response(): HasOne
    {
        return $this->hasOne(HiringFollowupResponse::class, 'invitation_id');
    }

    public function state(?\DateTimeInterface $now = null): FollowupInvitationState
    {
        return FollowupMilestoneStatus::invitationState(
            $this->revoked_at,
            $this->answered_at,
            $this->expires_at,
            $this->emailed_at,
            $this->email_failure_reason,
            $this->copied_at,
            $now ?? now(),
        );
    }

    /** Ainda pode ser usado: não revogado, não respondido e dentro do prazo. */
    public function isUsable(): bool
    {
        return $this->revoked_at === null && $this->answered_at === null && $this->expires_at->isFuture();
    }

    public function publicUrl(): string
    {
        return route('hiring-followup.public', $this->token);
    }
}
