<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HiringVacancyStage extends Model
{
    protected $fillable = [
        'hiring_vacancy_id',
        'name',
        'position',
        'is_hired',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_hired' => 'boolean',
        ];
    }

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(HiringVacancy::class, 'hiring_vacancy_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(HiringApplication::class, 'hiring_vacancy_stage_id');
    }
}
