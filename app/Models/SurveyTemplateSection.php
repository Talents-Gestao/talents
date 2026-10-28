<?php

namespace App\Models;

use App\Support\SurveyTemplateLock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyTemplateSection extends Model
{
    protected $fillable = [
        'survey_template_id',
        'title',
        'description',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $s) => SurveyTemplateLock::assertEditable((int) $s->survey_template_id));

        static::updating(function (self $s): void {
            if ($s->isDirty(['title', 'sort_order'])) {
                SurveyTemplateLock::assertEditable((int) $s->getOriginal('survey_template_id'));
            }
        });

        static::deleting(fn (self $s) => SurveyTemplateLock::assertEditable((int) $s->survey_template_id));
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SurveyTemplate::class, 'survey_template_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(SurveyTemplateQuestion::class)->orderBy('sort_order');
    }
}
