<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MethodologySurveyResponse extends Model
{
    protected $fillable = [
        'methodology_survey_id',
        'email',
        'session_token',
        'department_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(MethodologySurvey::class, 'methodology_survey_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(MethodologySurveyAnswer::class);
    }
}
