<?php

namespace App\Models;

use App\Enums\InterviewAnswerOrigin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterviewAnswer extends Model
{
    protected $fillable = [
        'interview_id',
        'question_id',
        'answer',
        'raw_quote',
        'origin',
        'evidence_missing',
        'reviewer_note',
    ];

    protected function casts(): array
    {
        return [
            'origin' => InterviewAnswerOrigin::class,
            'evidence_missing' => 'boolean',
        ];
    }

    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(InterviewQuestionnaireQuestion::class, 'question_id');
    }
}
