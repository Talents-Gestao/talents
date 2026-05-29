<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Survey;
use App\Models\SurveyTemplate;
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
            ->paginate(20);

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
            'min_responses_for_breakdown' => ['nullable', 'integer', 'min:1', 'max:1'],
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
            'min_responses_for_breakdown' => 1,
        ]);

        return redirect()->route('client.surveys.show', $survey)->with('success', 'Campanha criada.');
    }

    public function show(Request $request, Survey $survey): Response
    {
        $survey = $this->findSurvey($request, $survey);
        $survey->load('template');

        $publicUrl = route('survey.public', ['token' => $survey->public_token]);

        $completed = $survey->completedResponses()->count();
        $started = $survey->responses()->count();

        return Inertia::render('Client/Surveys/Show', [
            'survey' => $survey,
            'publicUrl' => $publicUrl,
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
            'min_responses_for_breakdown' => ['nullable', 'integer', 'min:1', 'max:1'],
        ]);

        $data['min_responses_for_breakdown'] = 1;

        $survey->update($data);

        return redirect()->route('client.surveys.show', $survey)->with('success', 'Campanha atualizada.');
    }

    public function destroy(Request $request, Survey $survey): RedirectResponse
    {
        $survey = $this->findSurvey($request, $survey);
        $survey->delete();

        return redirect()->route('client.surveys.index')->with('success', 'Campanha removida.');
    }
}
