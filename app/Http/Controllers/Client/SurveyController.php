<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\Survey;
use App\Services\Nr1\Nr1CampaignOverview;
use App\Models\SurveyAudienceLink;
use App\Models\SurveyTemplate;
use App\Support\Nr1GroupPrivacy;
use App\Support\SurveyPublicationGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SurveyController extends Controller
{
    private function companyId(Request $request): int
    {
        return (int) $request->user()->company_id;
    }

    private function findSurvey(Request $request, Survey $survey): Survey
    {
        abort_unless($survey->company_id === $this->companyId($request), 404);

        return $survey;
    }

    public function index(Request $request): Response
    {
        $surveys = Survey::query()
            ->where('company_id', $this->companyId($request))
            ->with('template')
            ->orderByDesc('id')
            ->paginate(20)
            // Resultados e plano só existem para a empresa depois de "Publicado" (T02): o link some até lá.
            ->through(fn (Survey $survey) => $survey->setAttribute('results_available', SurveyPublicationGate::allows($survey)));

        return Inertia::render('Client/Surveys/Index', [
            'surveys' => $surveys,
        ]);
    }

    public function create(Request $request): Response
    {
        $templates = SurveyTemplate::query()
            ->whereHas('companies', fn ($q) => $q->where('companies.id', $this->companyId($request)))
            ->where('is_active', true)
            ->get(['id', 'title', 'description']);

        return Inertia::render('Client/Surveys/Create', [
            'templates' => $templates,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'survey_template_id' => ['required', 'exists:survey_templates,id'],
            'title' => ['required', 'string', 'max:255'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['nullable', 'string', 'in:draft,active,closed'],
            'min_group_size' => ['nullable', 'integer', 'min:'.Nr1GroupPrivacy::FLOOR, 'max:1000'],
        ]);

        $template = SurveyTemplate::query()
            ->where('id', $data['survey_template_id'])
            ->whereHas('companies', fn ($q) => $q->where('companies.id', $this->companyId($request)))
            ->firstOrFail();

        $survey = Survey::create([
            'company_id' => $this->companyId($request),
            'survey_template_id' => $template->id,
            'title' => $data['title'],
            'public_token' => (string) Str::uuid(),
            'starts_at' => $data['starts_at'] ?? now(),
            'ends_at' => $data['ends_at'] ?? now()->addMonth(),
            'status' => $data['status'] ?? 'active',
            'min_group_size' => $data['min_group_size'] ?? Nr1GroupPrivacy::FLOOR,
            // Campanhas novas só mostram resultado à empresa depois de "Publicado" (T02).
            'requires_publication' => true,
        ]);

        return redirect()->route('client.surveys.show', $survey)->with('success', 'Campanha criada.');
    }

    public function show(Request $request, Survey $survey, Nr1CampaignOverview $overview): Response
    {
        $survey = $this->findSurvey($request, $survey);
        $survey->load('template');

        $publicUrl = route('survey.public', ['token' => $survey->public_token]);

        $completed = $survey->completedResponses()->count();
        $started = $survey->responses()->count();

        $v11 = $survey->usesReviewFlow();

        $audienceLinks = $survey->audienceLinks()->orderBy('audience')->get()->map(function (SurveyAudienceLink $link) use ($survey, $v11) {
            // Campanha NR-1 V11: link e QR só existem para a empresa enquanto a campanha e o link estão abertos.
            $open = ! $v11 || ($survey->publicParticipationClosureReason() === null && $link->closureReason() === null);

            return [
                'id' => $link->id,
                'audience' => $link->audience,
                'label' => $link->label(),
                'url' => $open ? route('survey.public', ['token' => $link->token]) : null,
                'qr_url' => $open ? route('client.surveys.audiences.qr', [$survey, $link]) : null,
                'open' => $open,
                'enabled' => $link->enabled,
                'opens_at' => $link->opens_at?->format('Y-m-d\TH:i'),
                'closes_at' => $link->closes_at?->format('Y-m-d\TH:i'),
            ];
        })->values();

        return Inertia::render('Client/Surveys/Show', [
            'survey' => $survey,
            'publicUrl' => $audienceLinks->isEmpty() && (! $v11 || $survey->publicParticipationClosureReason() === null) ? $publicUrl : null,
            'campaign' => $v11 ? $overview->build($survey, false) : null,
            'canRequestProposal' => $v11 && $request->user()->canAccess(PermissionModule::Pesquisas, PermissionAction::Edit),
            'audienceLinks' => $audienceLinks,
            'resultsAvailable' => SurveyPublicationGate::allows($survey),
            'stats' => [
                'started' => $started,
                'completed' => $completed,
            ],
        ]);
    }

    public function edit(Request $request, Survey $survey): Response
    {
        $survey = $this->findSurvey($request, $survey);

        return Inertia::render('Client/Surveys/Edit', [
            'survey' => $survey,
        ]);
    }

    public function update(Request $request, Survey $survey): RedirectResponse
    {
        $survey = $this->findSurvey($request, $survey);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', 'in:draft,active,closed'],
            'min_group_size' => ['nullable', 'integer', 'min:'.Nr1GroupPrivacy::FLOOR, 'max:1000'],
        ]);

        // Piso 5: a campanha só pode subir o mínimo, nunca descer (nem por omissão do campo).
        $data['min_group_size'] = max(Nr1GroupPrivacy::FLOOR, (int) ($data['min_group_size'] ?? $survey->min_group_size));

        $survey->update($data);

        return redirect()->route('client.surveys.show', $survey)->with('success', 'Campanha atualizada.');
    }
}
