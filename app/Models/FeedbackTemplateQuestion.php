<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FeedbackQuestionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackTemplateQuestion extends Model
{
    protected $fillable = [
        'feedback_template_section_id',
        'key',
        'body',
        'question_type',
        'options',
        'is_required',
        'sort_order',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'question_type' => FeedbackQuestionType::class,
            'options' => 'array',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
            'config' => 'array',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(FeedbackTemplateSection::class, 'feedback_template_section_id');
    }
}
