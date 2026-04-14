<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'strategic_calendar_access',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'rhid_password' => 'encrypted',
            'strategic_calendar_access' => 'boolean',
        ];
    }

    public function rhidAuditLogs(): HasMany
    {
        return $this->hasMany(RhidAuditLog::class);
    }

    public function rhidEspelhoImports(): HasMany
    {
        return $this->hasMany(RhidEspelhoImport::class);
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

    public function methodology(): HasOne
    {
        return $this->hasOne(CompanyMethodology::class);
    }

    public function methodologyFormTemplates(): BelongsToMany
    {
        return $this->belongsToMany(MethodologyFormTemplate::class, 'company_methodology_form_template')
            ->withTimestamps();
    }

    public function methodologySurveys(): HasMany
    {
        return $this->hasMany(MethodologySurvey::class);
    }

    /**
     * Metodologia Talents disponível quando a assinatura ativa inclui o módulo "metodologia" no plano.
     */
    public function hasMethodologyEnabled(): bool
    {
        $subscription = $this->subscriptions()
            ->where('status', 'active')
            ->with('plan.modules')
            ->latest()
            ->first();

        if (! $subscription?->plan) {
            return false;
        }

        return $subscription->plan->modules->contains('key', Module::KEY_METODOLOGIA);
    }

    /**
     * Calendário estratégico: override na empresa ou módulo no plano da assinatura ativa.
     */
    public function hasStrategicCalendarEnabled(): bool
    {
        if ($this->strategic_calendar_access === false) {
            return false;
        }

        if ($this->strategic_calendar_access === true) {
            return true;
        }

        $subscription = $this->subscriptions()
            ->where('status', 'active')
            ->with('plan.modules')
            ->latest()
            ->first();

        if (! $subscription?->plan) {
            return false;
        }

        return $subscription->plan->modules->contains('key', Module::KEY_CALENDARIO_ESTRATEGICO);
    }

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()->where('status', 'active')->latest()->first();
    }
}
