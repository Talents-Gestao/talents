<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo de feedback (universal, company_id nulo, ou da empresa). A estrutura fica nas versões
 * (FeedbackTemplateVersion); `sections()` devolve as seções da versão atual. Arquivado (archived_at)
 * some de «Novo feedback», mas os feedbacks existentes seguem normais.
 */
class FeedbackTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'title',
        'description',
        'is_default',
        'is_active',
        'version',
        'current_version_id',
        'archived_at',
        'archived_by_user_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'version' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Todo modelo nasce com a versão 1 (inclusive os criados por seeders e testes).
        static::created(function (FeedbackTemplate $template) {
            if ($template->current_version_id !== null) {
                return;
            }

            $version = $template->versions()->create([
                'number' => 1,
                'title' => $template->title,
                'description' => $template->description,
                'created_by' => $template->created_by,
            ]);

            $template->forceFill(['current_version_id' => $version->id])->saveQuietly();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(FeedbackTemplateVersion::class)->orderBy('number');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(FeedbackTemplateVersion::class, 'current_version_id');
    }

    /**
     * Seções da versão atual (subconsulta correlacionada: funciona em lazy e eager loading).
     */
    public function sections(): HasMany
    {
        return $this->hasMany(FeedbackTemplateSection::class)
            ->whereRaw('feedback_template_sections.feedback_template_version_id = (select ft.current_version_id from feedback_templates ft where ft.id = feedback_template_sections.feedback_template_id)')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /** Seções de todas as versões (histórico de chaves). */
    public function allSections(): HasMany
    {
        return $this->hasMany(FeedbackTemplateSection::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(FeedbackSession::class);
    }

    public function isGlobal(): bool
    {
        return $this->company_id === null;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
