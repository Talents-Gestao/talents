<?php

declare(strict_types=1);

namespace App\Support\Admin;

use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\CompanyInternalRegulation;
use App\Models\Survey;

/**
 * Agregados leves do painel operacional no detalhe da empresa (Admin).
 */
final class CompanyShowDashboardBuilder
{
    /**
     * @param  array<int, string>  $nr1ShellUrls
     * @return array<string, mixed>
     */
    public function build(Company $company, array $nr1ShellUrls): array
    {
        $users = $company->relationLoaded('users')
            ? $company->users
            : $company->users()->get(['id', 'is_active']);

        $surveys = $company->relationLoaded('surveys')
            ? $company->surveys
            : $company->surveys()->orderByDesc('id')->get(['id', 'title', 'status']);

        $purposeMaps = $company->relationLoaded('purposeMapCampaigns')
            ? $company->purposeMapCampaigns
            : $company->purposeMapCampaigns()->withCount('responses')->orderByDesc('id')->get();

        $latestSurvey = $surveys->first();
        $activeSubscription = $company->activeSubscription();
        $activePlanName = $activeSubscription?->plan?->name;
        $rhidEnabled = $company->hasRhidEnabled();
        $rhidConfigured = $company->rhidConfigured();
        $methodologyLinkedCount = $company->relationLoaded('methodologyFormTemplates')
            ? $company->methodologyFormTemplates->count()
            : $company->methodologyFormTemplates()->count();

        $recentSurveys = $surveys->take(5)->map(fn (Survey $survey): array => [
            'id' => $survey->id,
            'title' => $survey->title,
            'status' => $survey->status,
            'nr1_url' => $nr1ShellUrls[$survey->id] ?? null,
        ])->values()->all();

        $recentPurposeMaps = $purposeMaps->take(3)->map(static fn ($campaign): array => [
            'id' => $campaign->id,
            'title' => $campaign->title ?? ('Campanha #'.$campaign->id),
            'responses_count' => (int) ($campaign->responses_count ?? 0),
        ])->values()->all();

        $latestNr1Url = $latestSurvey
            ? ($nr1ShellUrls[$latestSurvey->id] ?? null)
            : null;

        return [
            'users_count' => $users->count(),
            'active_users_count' => $users->where('is_active', true)->count(),
            'surveys_count' => $surveys->count(),
            'active_surveys_count' => $surveys->where('status', 'active')->count(),
            'latest_survey' => $latestSurvey ? [
                'id' => $latestSurvey->id,
                'title' => $latestSurvey->title,
                'status' => $latestSurvey->status,
                'nr1_url' => $latestNr1Url,
            ] : null,
            'has_active_subscription' => $activeSubscription !== null,
            'active_plan_name' => $activePlanName,
            'employees_count' => CompanyEmployee::query()->where('company_id', $company->id)->count(),
            'regulations_count' => CompanyInternalRegulation::query()->where('company_id', $company->id)->count(),
            'purpose_maps_count' => $purposeMaps->count(),
            'rhid_enabled' => $rhidEnabled,
            'rhid_configured' => $rhidConfigured,
            'plan_includes_metodologia' => $company->hasMethodologyEnabled(),
            'methodology_templates_linked' => $methodologyLinkedCount,
            'has_complaints_token' => filled($company->complaints_public_token),
            'complaints_module_enabled' => $company->hasComplaintsEnabled(),
            'is_active' => (bool) $company->is_active,
            'pending_registration' => $company->hasPendingRegistration(),
            'recent_surveys' => $recentSurveys,
            'recent_purpose_maps' => $recentPurposeMaps,
            'shortcuts' => $this->shortcuts($company, $latestNr1Url),
        ];
    }

    /**
     * @return list<array{key: string, label: string, href: string, permission: string|null, enabled: bool}>
     */
    private function shortcuts(Company $company, ?string $latestNr1Url): array
    {
        $companyId = $company->id;

        return [
            [
                'key' => 'edit',
                'label' => 'Editar empresa',
                'href' => route('admin.companies.edit', $companyId),
                'permission' => 'companies',
                'enabled' => true,
            ],
            [
                'key' => 'users',
                'label' => 'Usuários',
                'href' => route('admin.companies.users.index', $companyId),
                'permission' => 'companies',
                'enabled' => true,
            ],
            [
                'key' => 'nr1',
                'label' => 'Painel NR-1',
                'href' => $latestNr1Url ?? route('admin.companies.show', ['company' => $companyId, 'tab' => 'empresa']),
                'permission' => 'companies',
                'enabled' => $latestNr1Url !== null,
            ],
            [
                'key' => 'ponto',
                'label' => 'Gestão de ponto',
                'href' => route('admin.companies.show', ['company' => $companyId, 'tab' => 'ponto']),
                'permission' => 'rhid',
                'enabled' => true,
            ],
            [
                'key' => 'colaboradores',
                'label' => 'Colaboradores',
                'href' => route('admin.companies.show', ['company' => $companyId, 'tab' => 'colaboradores']),
                'permission' => 'companies',
                'enabled' => true,
            ],
            [
                'key' => 'regulamento',
                'label' => 'Regulamento interno',
                'href' => route('admin.companies.show', ['company' => $companyId, 'tab' => 'regulamento']),
                'permission' => 'companies',
                'enabled' => true,
            ],
            [
                'key' => 'mapa',
                'label' => 'Mapa de Propósito',
                'href' => route('admin.mapa-proposito.index'),
                'permission' => 'companies',
                'enabled' => true,
            ],
            [
                'key' => 'avaliacoes',
                'label' => 'Avaliações',
                'href' => route('admin.client-evaluations.create', ['company_id' => $companyId]),
                'permission' => 'companies',
                'enabled' => true,
            ],
            [
                'key' => 'ferias',
                'label' => 'Férias',
                'href' => route('admin.ferias.index'),
                'permission' => 'ferias',
                'enabled' => true,
            ],
            [
                'key' => 'destaques',
                'label' => 'Destaques do mês',
                'href' => route('admin.destaques-mes.index', ['company_id' => $companyId]),
                'permission' => 'companies',
                'enabled' => true,
            ],
            [
                'key' => 'denuncias',
                'label' => 'Denúncias',
                'href' => route('admin.complaints.index'),
                'permission' => 'denuncias',
                'enabled' => true,
            ],
        ];
    }
}
