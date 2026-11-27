<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HiringVacancyStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Vaga dentro de um processo de acompanhamento. Uso interno da Talents:
 * o portal da empresa nunca recebe vagas, candidatos ou histórico.
 */
class HiringVacancy extends Model
{
    protected $fillable = [
        'hiring_process_id',
        'company_id',
        'title',
        'description',
        'status',
        'closed_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => HiringVacancyStatus::class,
            'closed_at' => 'datetime',
        ];
    }

    public function hiringProcess(): BelongsTo
    {
        return $this->belongsTo(HiringProcess::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(HiringVacancyStage::class)->orderBy('position')->orderBy('id');
    }

    public function communications(): HasMany
    {
        return $this->hasMany(HiringCommunication::class, 'hiring_vacancy_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(HiringApplication::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
