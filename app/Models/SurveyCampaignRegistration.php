<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyCampaignRegistration extends Model
{
    protected $fillable = [
        'survey_id',
        'company_id',
        'unit_name',
        'process_name',
        'legal_name_snapshot',
        'cnpj_snapshot',
        'eligible_count',
        'eligible_source',
        'exposure_groups',
        'aep_summary',
        'observation_summary',
        'existing_controls',
        'technical_responsible_name',
        'technical_responsible_role',
        'technical_responsible_registration',
        'approving_organization_name',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'eligible_count' => 'integer',
            'exposure_groups' => 'array',
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

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
