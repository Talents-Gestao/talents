<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Contato da empresa cliente para comunicações da Talents (vagas, pós-contratação, pesquisas).
 * Quem pode receber o quê é decidido só por App\Support\Hiring\CompanyContactEligibility.
 * Mudar e-mail ou telefone desfaz a conferência (reviewed_at).
 */
class CompanyContact extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'role',
        'email',
        'phone',
        'channel_email',
        'channel_whatsapp',
        'topics',
        'is_enabled',
        'reviewed_at',
        'reviewed_by',
        'is_primary',
        'responsible_user_id',
        'notes',
        'archived_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'channel_email' => 'boolean',
            'channel_whatsapp' => 'boolean',
            'topics' => 'array',
            'is_enabled' => 'boolean',
            'is_primary' => 'boolean',
            'reviewed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CompanyContact $contact): void {
            $email = mb_strtolower(trim((string) $contact->email));
            $contact->email = $email !== '' ? $email : null;

            $phone = trim((string) $contact->phone);
            $contact->phone = $phone !== '' ? $phone : null;
            $digits = preg_replace('/\D+/', '', $phone) ?? '';
            $contact->phone_digits = $digits !== '' ? $digits : null;

            // Contato alterado precisa ser conferido de novo.
            if ($contact->exists && $contact->isDirty(['email', 'phone']) && ! $contact->isDirty('reviewed_at')) {
                $contact->reviewed_at = null;
                $contact->reviewed_by = null;
            }
        });
    }

    /**
     * @param  Builder<CompanyContact>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function isReviewed(): bool
    {
        return $this->reviewed_at !== null;
    }

    /**
     * @return list<string>
     */
    public function topicValues(): array
    {
        return array_values(array_filter(array_map('strval', $this->topics ?? [])));
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CompanyContactEvent::class)->orderByDesc('created_at')->orderByDesc('id');
    }
}
