<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Survey extends Model
{
    protected $fillable = [
        'company_id',
        'survey_template_id',
        'title',
        'public_token',
        'starts_at',
        'ends_at',
        'status',
        'min_responses_for_breakdown',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SurveyTemplate::class, 'survey_template_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(SurveyResult::class);
    }

    public function insights(): HasMany
    {
        return $this->hasMany(SurveyInsight::class);
    }

    public function actionPlans(): HasMany
    {
        return $this->hasMany(ActionPlan::class);
    }

    public function completedResponses(): HasMany
    {
        return $this->responses()->whereNotNull('completed_at');
    }

    public function aiAnalyses(): HasMany
    {
        return $this->hasMany(AiAnalysis::class);
    }

    /**
     * Regra única para exibição e envio da pesquisa pública (evita POST direto fora da janela).
     *
     * @return 'inactive'|'not_started'|'ended'|null null = aceita participação
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
