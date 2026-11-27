<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedbackTemplateSection extends Model
{
    protected $fillable = [
        'feedback_template_id',
        'feedback_template_version_id',
        'key',
        'title',
        'description',
        'section_type',
        'audience',
        'sort_order',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'config' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Seção criada sem versão (seeders, testes, duplicação antiga): entra na versão atual do modelo.
        static::creating(function (FeedbackTemplateSection $section) {
            if ($section->feedback_template_version_id === null && $section->feedback_template_id !== null) {
                $section->feedback_template_version_id = FeedbackTemplate::withTrashed()
                    ->whereKey($section->feedback_template_id)
                    ->value('current_version_id');
            }

            if ($section->feedback_template_id === null && $section->feedback_template_version_id !== null) {
                $section->feedback_template_id = FeedbackTemplateVersion::query()
                    ->whereKey($section->feedback_template_version_id)
                    ->value('feedback_template_id');
            }
        });
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(FeedbackTemplate::class, 'feedback_template_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(FeedbackTemplateVersion::class, 'feedback_template_version_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(FeedbackTemplateQuestion::class)->orderBy('sort_order')->orderBy('id');
    }
}
