<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackSessionAnswer extends Model
{
    protected $fillable = [
        'feedback_session_id',
        'feedback_template_question_id',
        'value_text',
        'value_json',
    ];

    protected function casts(): array
    {
        return [
            'value_json' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(FeedbackSession::class, 'feedback_session_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(FeedbackTemplateQuestion::class, 'feedback_template_question_id');
    }
}
