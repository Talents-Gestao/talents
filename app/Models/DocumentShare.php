<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Exceptions\PsychosocialDocumentException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Compartilhamento explícito de uma versão de documento com um revisor externo.
 * Só a revogação muda depois de gravado; renovar a validade é um novo compartilhamento.
 */
class DocumentShare extends Model
{
    protected $fillable = [
        'company_id',
        'survey_id',
        'document_version_id',
        'reviewer_id',
        'approver_organization_id',
        'shared_by',
        'shared_at',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'shared_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DocumentShare $share): void {
            $document = PsychosocialDocumentVersion::query()->findOrFail($share->document_version_id)->document;

            if ((int) $document->company_id !== (int) $share->company_id || (int) $document->survey_id !== (int) $share->survey_id) {
                throw new PsychosocialDocumentException('Empresa e campanha do compartilhamento precisam ser as do documento.');
            }

            if ($share->expires_at === null || $share->expires_at->lte($share->shared_at ?? now())) {
                throw new PsychosocialDocumentException('A validade do compartilhamento precisa estar no futuro.');
            }

            $reviewerOrganizationId = UserWorkspace::query()
                ->where('user_id', $share->reviewer_id)
                ->where('is_active', true)
                ->where('role', UserRole::ExternalReviewer)
                ->value('approver_organization_id');

            if ($reviewerOrganizationId === null || (int) $reviewerOrganizationId !== (int) $share->approver_organization_id) {
                throw new PsychosocialDocumentException('O destinatário precisa ser revisor externo ativo da organização aprovadora informada.');
            }
        });

        static::updating(function (DocumentShare $share): void {
            $frozen = array_diff(array_keys($share->getDirty()), ['revoked_at', 'updated_at']);
            if ($frozen !== []) {
                throw new LogicException('Compartilhamento só pode ser revogado: '.implode(', ', $frozen).'.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Compartilhamentos não são apagados; revogue.');
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(PsychosocialDocumentVersion::class, 'document_version_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(ApproverOrganization::class, 'approver_organization_id');
    }

    public function sharedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(DocumentShareReview::class)->orderBy('id');
    }
}
