<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class PurposeMapCampaign extends Model
{
    protected $fillable = [
        'company_id',
        'title',
        'public_token',
        'status',
        'starts_at',
        'ends_at',
        'themes_analyzed_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'themes_analyzed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(PurposeMapResponse::class);
    }

    public function themes(): HasMany
    {
        return $this->hasMany(PurposeMapTheme::class);
    }

    public function insights(): HasMany
    {
        return $this->hasMany(PurposeMapInsight::class);
    }

    public function acceptsPublicResponses(): bool
    {
        return $this->publicParticipationClosureReason() === null;
    }

    /**
     * @return 'inactive'|'not_started'|'ended'|null
     */
    public function publicParticipationClosureReason(): ?string
    {
        if ($this->status !== 'active') {
            return 'inactive';
        }

        $now = Carbon::now();
        if ($this->starts_at !== null && $now->lt($this->starts_at)) {
            return 'not_started';
        }
        if ($this->ends_at !== null && $now->gt($this->ends_at)) {
            return 'ended';
        }

        return null;
    }
}
