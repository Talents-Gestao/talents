<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Link público de uma campanha para um público (Time ou Líderes), com janela própria de abertura.
 */
class SurveyAudienceLink extends Model
{
    public const AUDIENCE_TEAM = 'time';

    public const AUDIENCE_LEADERS = 'lideres';

    public const AUDIENCES = [self::AUDIENCE_TEAM, self::AUDIENCE_LEADERS];

    public const LABELS = [
        self::AUDIENCE_TEAM => 'Time',
        self::AUDIENCE_LEADERS => 'Líderes',
    ];

    protected $fillable = ['survey_id', 'audience', 'token', 'enabled', 'opens_at', 'closes_at'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function label(): string
    {
        return self::LABELS[$this->audience] ?? $this->audience;
    }

    /**
     * @return 'inactive'|'not_started'|'ended'|null null = aberto
     */
    public function closureReason(?Carbon $at = null): ?string
    {
        $at ??= now();

        if (! $this->enabled) {
            return 'inactive';
        }

        if ($this->opens_at && $at->lt($this->opens_at)) {
            return 'not_started';
        }

        if ($this->closes_at && $at->gt($this->closes_at)) {
            return 'ended';
        }

        return null;
    }
}
