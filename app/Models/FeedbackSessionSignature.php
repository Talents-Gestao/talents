<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FeedbackSignatureRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackSessionSignature extends Model
{
    protected $fillable = [
        'feedback_session_id',
        'role',
        'signer_name',
        'signer_email',
        'token',
        'sent_at',
        'signed_at',
        'cancelled_at',
        'cancelled_by_user_id',
        'signature_path',
        'ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'role' => FeedbackSignatureRole::class,
            'sent_at' => 'datetime',
            'signed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(FeedbackSession::class, 'feedback_session_id');
    }

    public function isSigned(): bool
    {
        return $this->signed_at !== null;
    }

    /** Convite cancelado ao reabrir o feedback para edição: o link deixa de aceitar assinatura. */
    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }
}
