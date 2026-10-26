<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\PsychosocialDocumentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * Rodada de revisão dos DOIS documentos finais de uma campanha (relatório executivo e relatório técnico + plano).
 * Estados: Em edição → Em revisão PASSEG → Alteração solicitada | Aprovado pela PASSEG → Publicado.
 * Publicada, a rodada não muda mais (T20): qualquer mudança é uma nova rodada, com novas versões.
 */
class PsychosocialReviewRound extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_CHANGES_REQUESTED = 'changes_requested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Em edição',
        self::STATUS_IN_REVIEW => 'Em revisão PASSEG',
        self::STATUS_CHANGES_REQUESTED => 'Alteração solicitada',
        self::STATUS_APPROVED => 'Aprovado pela PASSEG',
        self::STATUS_PUBLISHED => 'Publicado',
    ];

    /**
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_IN_REVIEW],
        self::STATUS_IN_REVIEW => [self::STATUS_CHANGES_REQUESTED, self::STATUS_APPROVED],
        self::STATUS_CHANGES_REQUESTED => [self::STATUS_IN_REVIEW],
        self::STATUS_APPROVED => [self::STATUS_PUBLISHED],
        self::STATUS_PUBLISHED => [],
    ];

    protected $fillable = [
        'survey_id',
        'company_id',
        'round_number',
        'status',
        'executive_version_id',
        'technical_version_id',
        'created_by',
        'sent_at',
        'sent_by',
        'approved_at',
        'approved_by',
        'published_at',
        'published_by',
        'protocol',
    ];

    protected function casts(): array
    {
        return [
            'round_number' => 'integer',
            'sent_at' => 'datetime',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PsychosocialReviewRound $round): void {
            if ($round->status !== self::STATUS_DRAFT) {
                throw new PsychosocialDocumentException('Toda rodada nasce «Em edição».');
            }
        });

        static::updating(function (PsychosocialReviewRound $round): void {
            $original = (string) $round->getOriginal('status');

            if ($original === self::STATUS_PUBLISHED) {
                throw new LogicException('Rodada publicada não pode ser alterada: abra uma nova revisão.');
            }

            if ($round->isDirty('status') && ! in_array($round->status, self::TRANSITIONS[$original] ?? [], true)) {
                throw new PsychosocialDocumentException(sprintf(
                    'Não é possível passar de «%s» para «%s».',
                    self::STATUS_LABELS[$original] ?? $original,
                    self::STATUS_LABELS[$round->status] ?? $round->status,
                ));
            }

            if ($round->isDirty(['survey_id', 'company_id', 'round_number'])) {
                throw new LogicException('Campanha, empresa e número da rodada não mudam.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Rodadas de revisão não são apagadas.');
        });
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function executiveVersion(): BelongsTo
    {
        return $this->belongsTo(PsychosocialDocumentVersion::class, 'executive_version_id');
    }

    public function technicalVersion(): BelongsTo
    {
        return $this->belongsTo(PsychosocialDocumentVersion::class, 'technical_version_id');
    }

    public function sentByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function publishedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PsychosocialReviewEvent::class, 'round_id')->orderBy('id');
    }

    public function acknowledgement(): HasOne
    {
        return $this->hasOne(PsychosocialReviewAcknowledgement::class, 'round_id');
    }

    public function pgrIntegration(): HasOne
    {
        return $this->hasOne(PsychosocialPgrIntegration::class, 'round_id');
    }
}
