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

    public function template(): BelongsTo
    {
        return $this->belongsTo(FeedbackTemplate::class, 'feedback_template_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(FeedbackTemplateQuestion::class)->orderBy('sort_order')->orderBy('id');
    }
}
