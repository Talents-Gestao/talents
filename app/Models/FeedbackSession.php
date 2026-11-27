<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FeedbackSessionStatus;
use App\Models\Concerns\HasRhidCollaborator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeedbackSession extends Model
{
    use HasRhidCollaborator;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'feedback_template_id',
        'feedback_template_version_id',
        'company_employee_id',
        'rhid_person_id',
        'employee_name',
        'employee_email',
        'leader_user_id',
        'created_by_user_id',
        'title',
        'status',
        'scheduled_at',
        'next_alignment_at',
        'completed_at',
        'section_extras',
    ];

    protected function casts(): array
    {
        return [
            'status' => FeedbackSessionStatus::class,
            'scheduled_at' => 'datetime',
            'next_alignment_at' => 'datetime',
            'completed_at' => 'datetime',
            'section_extras' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // O feedback usa a versão atual do modelo no momento da criação e fica nela até ser atualizado.
        static::creating(function (FeedbackSession $session) {
            if ($session->feedback_template_version_id === null && $session->feedback_template_id !== null) {
                $session->feedback_template_version_id = FeedbackTemplate::withTrashed()
                    ->whereKey($session->feedback_template_id)
                    ->value('current_version_id');
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(FeedbackTemplate::class, 'feedback_template_id');
    }

    /** Versão do modelo usada por este feedback (estrutura, título e descrição exibidos). */
    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(FeedbackTemplateVersion::class, 'feedback_template_version_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(CompanyEmployee::class, 'company_employee_id');
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(FeedbackSessionAnswer::class);
    }

    /** Assinaturas vigentes (as canceladas por «Reabrir para edição» ficam fora). */
    public function signatures(): HasMany
    {
        return $this->hasMany(FeedbackSessionSignature::class)->whereNull('cancelled_at');
    }

    /** Todas as assinaturas, inclusive as canceladas (histórico). */
    public function allSignatures(): HasMany
    {
        return $this->hasMany(FeedbackSessionSignature::class);
    }

    /** Sessão aberta: rascunho ou em preenchimento, sem convite de assinatura vigente. */
    public function isOpenForEditing(): bool
    {
        return in_array($this->status, [FeedbackSessionStatus::Draft, FeedbackSessionStatus::InProgress], true)
            && $this->signatures()->doesntExist();
    }

    public function recordings(): HasMany
    {
        return $this->hasMany(FeedbackRecording::class);
    }

    /** Gravação atual (não excluída); no máximo uma por feedback. */
    public function currentRecording(): ?FeedbackRecording
    {
        return $this->recordings()->latest('id')->first();
    }

    public function isFullySigned(): bool
    {
        return $this->signatures()->whereNull('signed_at')->doesntExist()
            && $this->signatures()->count() >= 2;
    }
}
