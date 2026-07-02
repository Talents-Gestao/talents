<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyNr1Report extends Model
{
    public const TYPE_EXECUTIVE = 'executive';

    public const TYPE_TECHNICAL_REFERRAL = 'technical_referral';

    protected $fillable = [
        'survey_id',
        'company_id',
        'type',
        'file_path',
        'file_name',
        'published_at',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null
            && filled($this->file_path);
    }
}
