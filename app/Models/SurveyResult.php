<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyResult extends Model
{
    protected $fillable = [
        'survey_id',
        'survey_template_section_id',
        'department_id',
        'average_score',
        'risk_level',
        'respondent_count',
        'meta',
        'method_version_id',
        'score_numerator',
        'score_denominator',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(SurveyTemplateSection::class, 'survey_template_section_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function methodVersion(): BelongsTo
    {
        return $this->belongsTo(SurveyMethodVersion::class, 'method_version_id');
    }
}
