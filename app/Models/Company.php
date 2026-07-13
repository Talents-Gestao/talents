<?php

namespace App\Models;

use App\Enums\PermissionModule;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    private ?Subscription $memoizedActiveSubscription = null;

    private bool $activeSubscriptionResolved = false;

    /** @var list<string>|null */
    private ?array $memoizedPlanModuleKeys = null;

    protected $fillable = [
        'name',
        'contact_email',
        'legal_name',
        'cnpj',
        'segment',
        'activity_branch',
        'collective_bargaining_month',
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
        'tasks_access',
        'rhid_access',
        'denuncias_access',
        'feedbacks_access',
        'ferias_access',
        'desligamento_access',
    ];

    protected function casts(): array
    {
        return [
            'collective_bargaining_month' => 'integer',
            'is_active' => 'boolean',
            'rhid_password' => 'encrypted',
            'strategic_calendar_access' => 'boolean',
            'tasks_access' => 'boolean',
            'rhid_access' => 'boolean',
            'denuncias_access' => 'boolean',
            'feedbacks_access' => 'boolean',
            'ferias_access' => 'boolean',
            'desligamento_access' => 'boolean',
        ];
    }

    public function taskBoards(): HasMany
    {
        return $this->hasMany(TaskBoard::class);
    }

    public function rhidAuditLogs(): HasMany
    {
        return $this->hasMany(RhidAuditLog::class);
    }

    public function rhidEspelhoImports(): HasMany
    {
        return $this->hasMany(RhidEspelhoImport::class);
    }

    public function rhidScheduleSetting(): HasOne
    {
        return $this->hasOne(CompanyRhidScheduleSetting::class);
    }

    public function rhidConfigured(): bool
    {
        return filled($this->rhid_email) && filled($this->rhid_password);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function registrationAdmin(): ?User
    {
        if (filled($this->contact_email)) {
            $byEmail = $this->users()->where('email', $this->contact_email)->first();
            if ($byEmail !== null) {
                return $byEmail;
            }
        }

        return $this->users()
            ->where('role', UserRole::CompanyAdmin)
            ->orderBy('id')
            ->first();
    }

    public function hasPendingRegistration(): bool
    {
        $admin = $this->registrationAdmin();

        return $admin !== null && ! $admin->hasCompletedRegistration();
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

    public function employees(): HasMany
    {
        return $this->hasMany(CompanyEmployee::class);
    }

    public function feedbackSessions(): HasMany
    {
        return $this->hasMany(FeedbackSession::class);
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
     * Direcionamento Estratégico disponível quando a assinatura ativa inclui o módulo "metodologia" no plano.
     */
    public function hasMethodologyEnabled(): bool
    {
        return in_array(Module::KEY_METODOLOGIA, $this->activePlanModuleKeys(), true);
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

        return in_array(Module::KEY_CALENDARIO_ESTRATEGICO, $this->activePlanModuleKeys(), true);
    }

    /**
     * Janela de visualização do calendário estratégico conforme o plano (null = sem limite).
     *
     * @return array{start: \Carbon\Carbon, end: \Carbon\Carbon, label: string, period: \App\Enums\StrategicCalendarViewPeriod}|null
     */
    public function strategicCalendarVisibleRange(?\Carbon\Carbon $now = null): ?array
    {
        return \App\Support\StrategicCalendarPeriod::forCompany($this, $now);
    }

    /**
     * Módulo Tarefas: override na empresa ou chave no plano da assinatura ativa.
     */
    public function hasTasksEnabled(): bool
    {
        if ($this->tasks_access === false) {
            return false;
        }

        if ($this->tasks_access === true) {
            return true;
        }

        return $this->subscriptionHasModuleKey(Module::KEY_TAREFAS);
    }

    /**
     * Módulo RHID: override na empresa ou chave no plano da assinatura ativa.
     */
    public function hasRhidEnabled(): bool
    {
        if ($this->rhid_access === false) {
            return false;
        }

        if ($this->rhid_access === true) {
            return true;
        }

        return $this->subscriptionHasModuleKey(Module::KEY_RHID);
    }

    /**
     * Canal de denúncias: override na empresa ou chave no plano da assinatura ativa.
     */
    public function hasComplaintsEnabled(): bool
    {
        if ($this->denuncias_access === false) {
            return false;
        }

        if ($this->denuncias_access === true) {
            return true;
        }

        return $this->subscriptionHasModuleKey(Module::KEY_DENUNCIAS);
    }

    public function hasFeedbacksEnabled(): bool
    {
        if ($this->feedbacks_access === false) {
            return false;
        }

        if ($this->feedbacks_access === true) {
            return true;
        }

        return $this->subscriptionHasModuleKey(Module::KEY_FEEDBACKS);
    }

    public function hasFeriasEnabled(): bool
    {
        if ($this->ferias_access === false) {
            return false;
        }

        if ($this->ferias_access === true) {
            return true;
        }

        return $this->subscriptionHasModuleKey(Module::KEY_FERIAS);
    }

    public function hasDesligamentoEnabled(): bool
    {
        if ($this->desligamento_access === false) {
            return false;
        }

        if ($this->desligamento_access === true) {
            return true;
        }

        return $this->subscriptionHasModuleKey(Module::KEY_DESLIGAMENTO);
    }

    public function activeSubscription(): ?Subscription
    {
        if ($this->activeSubscriptionResolved) {
            return $this->memoizedActiveSubscription;
        }

        $this->activeSubscriptionResolved = true;
        $this->memoizedActiveSubscription = $this->subscriptions()
            ->where('status', 'active')
            ->with('plan.modules')
            ->latest()
            ->first();

        return $this->memoizedActiveSubscription;
    }

    /**
     * Chaves de módulos do plano da assinatura ativa (uma query por empresa/request).
     *
     * @return list<string>
     */
    public function activePlanModuleKeys(): array
    {
        if ($this->memoizedPlanModuleKeys !== null) {
            return $this->memoizedPlanModuleKeys;
        }

        $subscription = $this->activeSubscription();

        if (! $subscription?->plan) {
            $this->memoizedPlanModuleKeys = [];

            return $this->memoizedPlanModuleKeys;
        }

        $this->memoizedPlanModuleKeys = $subscription->plan->modules
            ->pluck('key')
            ->all();

        return $this->memoizedPlanModuleKeys;
    }

    public function subscriptionHasModuleKey(string $key): bool
    {
        return in_array($key, $this->activePlanModuleKeys(), true);
    }

    public function hasModuleEnabled(PermissionModule $module): bool
    {
        return match ($module) {
            PermissionModule::Pesquisas,
            PermissionModule::PlanosAcao,
            PermissionModule::DepartamentosCargos,
            PermissionModule::Relatorios,
            PermissionModule::ConfiguracoesEmpresa,
            PermissionModule::Usuarios,
            PermissionModule::Capacitacao => $this->subscriptionHasModuleKey(Module::KEY_NR1),
            PermissionModule::Metodologia => $this->hasMethodologyEnabled(),
            PermissionModule::CalendarioEstrategico => $this->hasStrategicCalendarEnabled(),
            PermissionModule::Rhid => $this->hasRhidEnabled(),
            PermissionModule::Tarefas => $this->hasTasksEnabled(),
            PermissionModule::Denuncias => $this->hasComplaintsEnabled(),
            PermissionModule::Feedbacks => $this->hasFeedbacksEnabled(),
            PermissionModule::Ferias => $this->hasFeriasEnabled(),
            PermissionModule::Desligamento => $this->hasDesligamentoEnabled(),
        };
    }

    /**
     * Valores de PermissionModule ativos para esta empresa (plano / configuração).
     *
     * @return list<string>
     */
    public function activePermissionModuleValues(): array
    {
        $out = [];
        foreach (PermissionModule::all() as $m) {
            if ($this->hasModuleEnabled($m)) {
                $out[] = $m->value;
            }
        }

        return $out;
    }
}
