<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MethodologySurveyAnswer extends Model
{
    protected $fillable = [
        'methodology_survey_response_id',
        'methodology_form_question_id',
        'value_numeric',
        'value_text',
    ];

    protected function casts(): array
    {
        return [
            'value_numeric' => 'integer',
        ];
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(MethodologySurveyResponse::class, 'methodology_survey_response_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(MethodologyFormQuestion::class, 'methodology_form_question_id');
    }
}
