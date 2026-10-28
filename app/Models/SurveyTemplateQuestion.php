<?php

namespace App\Models;

use App\Support\SurveyTemplateLock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyTemplateQuestion extends Model
{
    protected $fillable = [
        'survey_template_section_id',
        'body',
        'reverse_score',
        'weight',
        'response_scale',
        'sort_order',
    ];

    /** Campos que mudam o resultado ou a leitura do item; ficam travados com a campanha iniciada. */
    private const LOCKED_FIELDS = ['survey_template_section_id', 'body', 'reverse_score', 'weight', 'response_scale', 'sort_order'];

    protected static function booted(): void
    {
        static::creating(fn (self $q) => SurveyTemplateLock::assertEditable(SurveyTemplateLock::templateIdForSection((int) $q->survey_template_section_id)));

        static::updating(function (self $q): void {
            if ($q->isDirty(self::LOCKED_FIELDS)) {
                SurveyTemplateLock::assertEditable(SurveyTemplateLock::templateIdForSection((int) $q->getOriginal('survey_template_section_id')));
            }
        });

        static::deleting(fn (self $q) => SurveyTemplateLock::assertEditable(SurveyTemplateLock::templateIdForSection((int) $q->survey_template_section_id)));
    }

    protected function casts(): array
    {
        return [
            'reverse_score' => 'boolean',
            'weight' => 'float',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(SurveyTemplateSection::class, 'survey_template_section_id');
    }
}
