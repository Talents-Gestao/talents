<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Candidato numa vaga, numa etapa da própria vaga. */
class HiringApplication extends Model
{
    protected $fillable = [
        'hiring_vacancy_id',
        'talent_candidate_id',
        'hiring_vacancy_stage_id',
        'sort_order',
        'hired_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'hired_at' => 'datetime',
        ];
    }

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(HiringVacancy::class, 'hiring_vacancy_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(TalentCandidate::class, 'talent_candidate_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(HiringVacancyStage::class, 'hiring_vacancy_stage_id');
    }

    public function assessment(): HasOne
    {
        return $this->hasOne(HiringAssessment::class);
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class, 'hiring_application_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(HiringApplicationEvent::class)->orderByDesc('created_at')->orderByDesc('id');
    }
}
