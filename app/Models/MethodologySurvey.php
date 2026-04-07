<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class MethodologySurvey extends Model
{
    protected $fillable = [
        'company_id',
        'methodology_form_template_id',
        'title',
        'public_token',
        'status',
        'starts_at',
        'ends_at',
        'collect_email',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'collect_email' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MethodologyFormTemplate::class, 'methodology_form_template_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(MethodologySurveyResponse::class);
    }

    public function completedResponses(): HasMany
    {
        return $this->responses()->whereNotNull('completed_at');
    }

    /**
     * @return 'inactive'|'not_started'|'ended'|null
     */
    public function publicParticipationClosureReason(?Carbon $at = null): ?string
    {
        $at ??= now();

        if ($this->status !== 'active') {
            return 'inactive';
        }

        if ($this->starts_at && $at->lt($this->starts_at)) {
            return 'not_started';
        }

        if ($this->ends_at && $at->gt($this->ends_at)) {
            return 'ended';
        }

        return null;
    }

    public function acceptsPublicResponses(?Carbon $at = null): bool
    {
        return $this->publicParticipationClosureReason($at) === null;
    }
}
