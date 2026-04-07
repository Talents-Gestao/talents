<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\MethodologyFormTemplate;
use App\Models\MethodologySurvey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MethodologySurveyController extends Controller
{
    private function guardCompany(Request $request): Company
    {
        $company = $request->user()->company;
        abort_unless($company && $company->hasMethodologyEnabled(), 403);

        return $company;
    }

    public function index(Request $request): Response
    {
        $company = $this->guardCompany($request);

        $surveys = MethodologySurvey::query()
            ->where('company_id', $company->id)
            ->with(['template'])
            ->withCount('completedResponses')
            ->orderByDesc('id')
            ->paginate(15);

        return Inertia::render('Client/Methodology/Surveys/Index', [
            'surveys' => $surveys,
        ]);
    }

    public function create(Request $request): Response
    {
        $company = $this->guardCompany($request);

        $templates = $company->methodologyFormTemplates()
            ->where('is_active', true)
            ->withCount('sections')
            ->orderBy('title')
            ->get(['id', 'title', 'description', 'step_number']);

        return Inertia::render('Client/Methodology/Surveys/Create', [
            'templates' => $templates,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->guardCompany($request);

        $data = $request->validate([
            'methodology_form_template_id' => ['required', 'exists:methodology_form_templates,id'],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,draft,closed'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'collect_email' => ['boolean'],
        ]);

        $template = MethodologyFormTemplate::query()->findOrFail($data['methodology_form_template_id']);
        abort_unless(
            $company->methodologyFormTemplates()->whereKey($template->id)->exists(),
            403,
            'Template não vinculado à sua empresa.'
        );

        $survey = MethodologySurvey::create([
            'company_id' => $company->id,
            'methodology_form_template_id' => $template->id,
            'title' => $data['title'],
            'public_token' => (string) Str::uuid(),
            'status' => $data['status'] ?? 'active',
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'collect_email' => $data['collect_email'] ?? false,
        ]);

        return redirect()
            ->route('client.metodologia.pesquisa-satisfacao.show', $survey)
            ->with('success', 'Pesquisa criada. Compartilhe o link público com os colaboradores.');
    }

    public function show(Request $request, MethodologySurvey $survey): Response
    {
        $company = $this->guardCompany($request);
        abort_unless($survey->company_id === $company->id, 403);

        $survey->load(['template.sections.questions']);
        $survey->loadCount('completedResponses');

        $publicUrl = url('/satisfacao/'.$survey->public_token);

        return Inertia::render('Client/Methodology/Surveys/Show', [
            'survey' => $survey,
            'publicUrl' => $publicUrl,
        ]);
    }

    public function edit(Request $request, MethodologySurvey $survey): Response
    {
        $company = $this->guardCompany($request);
        abort_unless($survey->company_id === $company->id, 403);

        return Inertia::render('Client/Methodology/Surveys/Edit', [
            'survey' => $survey,
        ]);
    }

    public function update(Request $request, MethodologySurvey $survey): RedirectResponse
    {
        $company = $this->guardCompany($request);
        abort_unless($survey->company_id === $company->id, 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:active,draft,closed'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'collect_email' => ['boolean'],
        ]);

        $survey->update([
            'title' => $data['title'],
            'status' => $data['status'],
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'collect_email' => $data['collect_email'] ?? false,
        ]);

        return redirect()
            ->route('client.metodologia.pesquisa-satisfacao.show', $survey)
            ->with('success', 'Pesquisa atualizada.');
    }

    public function destroy(Request $request, MethodologySurvey $survey): RedirectResponse
    {
        $company = $this->guardCompany($request);
        abort_unless($survey->company_id === $company->id, 403);

        $survey->delete();

        return redirect()
            ->route('client.metodologia.pesquisa-satisfacao.index')
            ->with('success', 'Pesquisa removida.');
    }
}
