<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Anexo interno do candidato (restrito: contratacao_anexos).
 * Nunca entra em exportação, relatório, IA, dossiê nem portal da empresa, e não é analisado automaticamente.
 */
class TalentCandidateAttachment extends Model
{
    public const DISK = 'local';

    protected $fillable = [
        'talent_candidate_id',
        'original_name',
        'path',
        'mime',
        'size',
        'sha256',
        'sender_name',
        'received_on',
        'purpose',
        'privacy_confirmed_at',
        'uploaded_by',
    ];

    /** O caminho no disco nunca sai do servidor. */
    protected $hidden = ['path'];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'received_on' => 'date',
            'privacy_confirmed_at' => 'datetime',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(TalentCandidate::class, 'talent_candidate_id');
    }

    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
