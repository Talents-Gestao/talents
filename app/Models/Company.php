<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'name',
        'contact_email',
        'legal_name',
        'cnpj',
        'segment',
        'address_street',
        'address_neighborhood',
        'address_city',
        'address_state',
        'address_zip',
        'tax_regime',
        'employee_count_estimate',
        'is_active',
        'complaints_public_token',
        'rhid_base_url',
        'rhid_email',
        'rhid_password',
        'rhid_domain',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'rhid_password' => 'encrypted',
        ];
    }

    public function rhidAuditLogs(): HasMany
    {
        return $this->hasMany(RhidAuditLog::class);
    }

    public function rhidConfigured(): bool
    {
        return filled($this->rhid_email) && filled($this->rhid_password);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    public function surveys(): HasMany
    {
        return $this->hasMany(Survey::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function surveyTemplates(): BelongsToMany
    {
        return $this->belongsToMany(SurveyTemplate::class, 'company_survey_template')
            ->withTimestamps();
    }

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()->where('status', 'active')->latest()->first();
    }
}
