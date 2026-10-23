<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Agregado de grupo recalculado sobre as respostas originais ("Outros Setores").
 * A composição (quais setores) nunca é gravada aqui.
 */
class SurveyGroupResult extends Model
{
    public const GROUP_OTHERS = 'others';

    protected $fillable = [
        'survey_id',
        'survey_template_section_id',
        'group_key',
        'average_score',
        'risk_level',
        'respondent_count',
        'method_version_id',
        'score_numerator',
        'score_denominator',
        'meta',
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
}
