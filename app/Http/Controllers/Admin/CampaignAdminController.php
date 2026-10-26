<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\Nr1CampaignException;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Nr1Training;
use App\Models\Survey;
use App\Models\SurveyAudienceLink;
use App\Models\SurveyCampaignRegistration;
use App\Services\Nr1\Nr1CampaignOverview;
use App\Services\Nr1\Nr1TrainingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Campanha NR-1 para a Talents: marca treinamentos como contratados (modalidade, público, escopo, datas, link ou arquivo,
 * responsável; presencial: local, data e hora, facilitador, capacidade, presença) e cadastra o total de elegíveis.
 */
class CampaignAdminController extends Controller
{
    public function __construct(
        private readonly Nr1CampaignOverview $overview,
        private readonly Nr1TrainingService $trainings,
    ) {}

    public function show(Company $company, Survey $survey): Response
    {
        $this->authorizeSurvey($company, $survey);

        $registration = SurveyCampaignRegistration::query()->where('survey_id', $survey->id)->first();
        $open = $survey->publicParticipationClosureReason() === null;

        return Inertia::render('Admin/Surveys/Campaign', [
            'company' => ['id' => $company->id, 'name' => $company->name],
            'survey' => ['id' => $survey->id, 'title' => $survey->title],
            'campaign' => $this->overview->build($survey, true),
            'audienceLinks' => $survey->audienceLinks()->orderBy('audience')->get()->map(fn (SurveyAudienceLink $link) => [
                'id' => $link->id,
                'label' => $link->label(),
                'open' => $open && $link->closureReason() === null,
                'url' => $open && $link->closureReason() === null ? route('survey.public', ['token' => $link->token]) : null,
            ])->values(),
            'eligible' => [
                'count' => $registration?->eligible_count,
                'source' => $registration?->eligible_source,
                'min' => $survey->completedResponses()->count(),
            ],
            'urls' => [
                'contract' => route('admin.companies.surveys.campaign.contract', [$company->id, $survey->id]),
                'uncontract' => route('admin.companies.surveys.campaign.uncontract', [$company->id, $survey->id]),
                'eligible' => route('admin.companies.surveys.campaign.eligible', [$company->id, $survey->id]),
                'registration' => route('admin.companies.surveys.registration.edit', [$company->id, $survey->id]),
            ],
        ]);
    }

    public function contract(Request $request, Company $company, Survey $survey): RedirectResponse
    {
        $this->authorizeSurvey($company, $survey);

        $data = $request->validate([
            'modality' => ['required', 'in:'.implode(',', Nr1Training::MODALITIES)],
            'audience' => ['required', 'in:'.implode(',', SurveyAudienceLink::AUDIENCES)],
            'scope' => ['required', 'string', 'max:2000'],
            'price_cents' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date'],
            'link_url' => ['nullable', 'url', 'max:500'],
            'material' => ['nullable', 'file', 'max:20480', 'mimes:pdf,mp4,pptx,docx,jpg,jpeg,png,zip'],
            'responsible' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'event_at' => ['nullable', 'date'],
            'facilitator' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'attendance' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'released' => ['nullable', 'boolean'],
        ]);

        try {
            $this->trainings->contract($survey, $request->user(), $data['modality'], $data['audience'], $data, $request->file('material'));
        } catch (Nr1CampaignException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Treinamento marcado como contratado.');
    }

    public function uncontract(Request $request, Company $company, Survey $survey): RedirectResponse
    {
        $this->authorizeSurvey($company, $survey);

        $data = $request->validate([
            'modality' => ['required', 'in:'.implode(',', Nr1Training::MODALITIES)],
            'audience' => ['required', 'in:'.implode(',', SurveyAudienceLink::AUDIENCES)],
        ]);

        try {
            $this->trainings->uncontract($survey, $request->user(), $data['modality'], $data['audience']);
        } catch (Nr1CampaignException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Contratação removida.');
    }

    public function updateEligible(Request $request, Company $company, Survey $survey): RedirectResponse
    {
        $this->authorizeSurvey($company, $survey);

        $completed = $survey->completedResponses()->count();
        $data = $request->validate([
            'eligible_count' => ['required', 'integer', 'min:'.max(1, $completed), 'max:1000000'],
            'eligible_source' => ['required', 'string', 'min:3', 'max:255'],
        ], [
            'eligible_count.min' => 'O total de elegíveis não pode ser menor que o número de respostas ('.$completed.').',
        ]);

        SurveyCampaignRegistration::query()->updateOrCreate(
            ['survey_id' => $survey->id],
            [
                'company_id' => $survey->company_id,
                'eligible_count' => $data['eligible_count'],
                'eligible_source' => trim($data['eligible_source']),
                'updated_by' => $request->user()->id,
            ],
        );

        return back()->with('success', 'Total de elegíveis atualizado.');
    }

    private function authorizeSurvey(Company $company, Survey $survey): void
    {
        abort_unless((int) $survey->company_id === (int) $company->id, 404);
        abort_unless($survey->usesReviewFlow(), 404);
    }
}
