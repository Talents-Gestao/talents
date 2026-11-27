<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Candidato do cadastro de Contratação. A pessoa é identificada por solides_id ou e-mail
 * normalizado, nunca pelo nome. Excluir = arquivar (archived_at), preservando candidaturas.
 */
class TalentCandidate extends Model
{
    public const CONTACT_PREFERENCES = ['email', 'whatsapp', 'phone'];

    protected $fillable = [
        'solides_id',
        'name',
        'email',
        'phone',
        'email_reviewed',
        'phone_reviewed',
        'email_opt_in',
        'contact_preference',
        'source',
        'current_role',
        'current_company',
        'notes',
        'archived_at',
        'archived_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'email_reviewed' => 'boolean',
            'phone_reviewed' => 'boolean',
            'email_opt_in' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (TalentCandidate $candidate): void {
            $email = self::normalizeEmail($candidate->email);
            $candidate->email = $email;
            $candidate->email_normalized = $email;

            $phone = trim((string) $candidate->phone);
            $candidate->phone = $phone !== '' ? $phone : null;
            $digits = preg_replace('/\D+/', '', $phone) ?? '';
            $candidate->phone_digits = $digits !== '' ? $digits : null;

            // Contato alterado perde a conferência e a autorização anteriores.
            if ($candidate->exists && $candidate->isDirty('email')) {
                if (! $candidate->isDirty('email_reviewed')) {
                    $candidate->email_reviewed = false;
                }
                if (! $candidate->isDirty('email_opt_in')) {
                    $candidate->email_opt_in = false;
                }
            }
            if ($candidate->exists && $candidate->isDirty('phone') && ! $candidate->isDirty('phone_reviewed')) {
                $candidate->phone_reviewed = false;
            }
        });
    }

    /** Telefone internacional: começa com + e tem de 8 a 15 dígitos (E.164), aceitando espaços, parênteses e hífens. */
    public static function isValidInternationalPhone(?string $phone): bool
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return true;
        }
        if (preg_match('/^\+[\d\s().-]+$/', $phone) !== 1) {
            return false;
        }
        $digits = strlen(preg_replace('/\D+/', '', $phone) ?? '');

        return $digits >= 8 && $digits <= 15;
    }

    public static function contactPreferenceLabel(string $value): string
    {
        return match ($value) {
            'email' => 'E-mail',
            'whatsapp' => 'WhatsApp',
            'phone' => 'Telefone',
            default => $value,
        };
    }

    public static function normalizeEmail(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email !== '' ? $email : null;
    }

    /**
     * @param  Builder<TalentCandidate>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function applications(): HasMany
    {
        return $this->hasMany(HiringApplication::class);
    }

    /** Anexos internos (restritos a contratacao_anexos). */
    public function attachments(): HasMany
    {
        return $this->hasMany(TalentCandidateAttachment::class);
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class, 'talent_candidate_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(TalentCandidateAuditLog::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchResult(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'current_role' => $this->current_role,
        ];
    }
}
