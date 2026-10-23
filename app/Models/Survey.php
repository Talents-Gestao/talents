<?php

namespace App\Models;

use App\Services\SurveyInstrumentVersionResolver;
use App\Support\Nr1GroupPrivacy;
use App\Support\Nr1InstrumentMatrix;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Survey extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'survey_template_id',
        'title',
        'public_token',
        'starts_at',
        'ends_at',
        'status',
        'min_responses_for_breakdown',
        'min_group_size',
        'answers_reconstructed_at',
        'deleted_by',
        'archive_path',
        'method_version_id',
        'instrument_version_id',
    ];

    protected static function booted(): void
    {
        // Campanha sem método explícito nasce em production_v1; talents39_v1 só por escolha explícita.
        static::saving(function (Survey $survey): void {
            if ($survey->min_group_size !== null && (int) $survey->min_group_size < Nr1GroupPrivacy::FLOOR) {
                throw new \InvalidArgumentException('O mínimo de respondentes por grupo não pode ser menor que '.Nr1GroupPrivacy::FLOOR.'.');
            }
        });

        static::creating(function (Survey $survey): void {
            $survey->method_version_id ??= SurveyMethodVersion::idForCode(SurveyMethodVersion::CODE_PRODUCTION_V1);

            if ($survey->instrument_version_id === null && $survey->survey_template_id !== null) {
                $survey->instrument_version_id = app(SurveyInstrumentVersionResolver::class)
                    ->resolveForTemplate((int) $survey->survey_template_id)
                    ->id;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'answers_reconstructed_at' => 'datetime',
        ];
    }

    /**
     * Campanhas V11 (talents39) não coletam faixa etária nem tempo de casa; as antigas mantêm o dado.
     */
    public function collectsDemographics(): bool
    {
        // Consulta direta: não carrega relações no model, que vai inteiro para a página pública.
        $methodCode = SurveyMethodVersion::query()->whereKey($this->method_version_id)->value('code');
        $instrumentCode = SurveyInstrumentVersion::query()->whereKey($this->instrument_version_id)->value('instrument_code');

        return $methodCode !== SurveyMethodVersion::CODE_TALENTS39_V1
            && $instrumentCode !== Nr1InstrumentMatrix::CODE_TALENTS39;
    }

    public function hasReconstructedAnswers(): bool
    {
        return $this->answers_reconstructed_at !== null;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SurveyTemplate::class, 'survey_template_id');
    }

    public function methodVersion(): BelongsTo
    {
        return $this->belongsTo(SurveyMethodVersion::class, 'method_version_id');
    }

    public function instrumentVersion(): BelongsTo
    {
        return $this->belongsTo(SurveyInstrumentVersion::class, 'instrument_version_id');
    }

    public function campaignRegistration(): HasOne
    {
        return $this->hasOne(SurveyCampaignRegistration::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(SurveyResult::class);
    }

    public function audienceLinks(): HasMany
    {
        return $this->hasMany(SurveyAudienceLink::class);
    }

    public function groupResults(): HasMany
    {
        return $this->hasMany(SurveyGroupResult::class);
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

    public function nr1Reports(): HasMany
    {
        return $this->hasMany(SurveyNr1Report::class);
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
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
