<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSurveyCampaignRegistrationRequest;
use App\Models\Company;
use App\Models\Survey;
use App\Models\SurveyCampaignRegistration;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cadastro técnico da campanha (Admin Talents). Abrir a tela não cria linha; a primeira gravação cria.
 */
class SurveyCampaignRegistrationAdminController extends Controller
{
    private const CONTENT_FIELDS = [
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
    ];

    public function edit(Company $company, Survey $survey): Response
    {
        $this->assertSurveyBelongsToCompany($company, $survey);

        $registration = SurveyCampaignRegistration::query()
            ->where('survey_id', $survey->id)
            ->with('updatedBy:id,name')
            ->first();

        return Inertia::render('Admin/CampaignRegistration/Edit', [
            'company' => $company->only(['id', 'name', 'legal_name', 'cnpj']),
            'survey' => $survey->only(['id', 'title']),
            'registration' => $registration
                ? $registration->only(self::CONTENT_FIELDS)
                : array_fill_keys(self::CONTENT_FIELDS, null),
            'lastUpdate' => $registration ? [
                'at' => $registration->updated_at?->toIso8601String(),
                'by' => $registration->updatedBy?->name,
            ] : null,
        ]);
    }

    public function update(UpdateSurveyCampaignRegistrationRequest $request, Company $company, Survey $survey): RedirectResponse
    {
        $this->assertSurveyBelongsToCompany($company, $survey);

        SurveyCampaignRegistration::query()->updateOrCreate(
            ['survey_id' => $survey->id],
            array_merge($request->registrationData(), [
                'company_id' => $survey->company_id,
                'updated_by' => $request->user()->id,
            ]),
        );

        return redirect()
            ->route('admin.companies.surveys.registration.edit', [$company->id, $survey->id])
            ->with('success', 'Cadastro técnico salvo.');
    }

    private function assertSurveyBelongsToCompany(Company $company, Survey $survey): void
    {
        abort_unless((int) $survey->company_id === (int) $company->id, 404);
    }
}
