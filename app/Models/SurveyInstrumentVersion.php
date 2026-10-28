<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Nr1InstrumentMatrix;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyInstrumentVersion extends Model
{
    protected $fillable = [
        'survey_template_id',
        'instrument_code',
        'version_label',
        'source',
        'item_matrix',
        'item_matrix_sha256',
        'validation_reference',
        'polarity_audit_status',
        'polarity_audit_accepted_at',
        'polarity_audit_accepted_by',
        'frozen_at',
    ];

    protected function casts(): array
    {
        return [
            'item_matrix' => 'array',
            'polarity_audit_accepted_at' => 'datetime',
            'frozen_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SurveyTemplate::class, 'survey_template_id');
    }

    public function surveys(): HasMany
    {
        return $this->hasMany(Survey::class, 'instrument_version_id');
    }

    public function isPolarityAuditPending(): bool
    {
        return $this->polarity_audit_status === Nr1InstrumentMatrix::AUDIT_PENDING;
    }
}
