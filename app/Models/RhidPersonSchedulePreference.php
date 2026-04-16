<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RhidPersonSchedulePreference extends Model
{
    protected $table = 'rhid_person_schedule_preferences';

    protected $fillable = [
        'company_id',
        'id_person',
        'use_second_lunch_interval',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_person' => 'integer',
            'use_second_lunch_interval' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
