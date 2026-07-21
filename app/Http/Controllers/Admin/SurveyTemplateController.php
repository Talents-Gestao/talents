<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Http\Controllers\Controller;
use App\Models\ExitInterview;
use App\Models\Survey;
use App\Models\SurveyTemplate;
use App\Models\SurveyTemplateQuestion;
use App\Models\SurveyTemplateSection;
use App\Services\SurveyTemplateVersioningService;
use App\Support\Offboarding\DesligamentoCompanyContext;
use App\Support\Offboarding\ExitInterviewScript;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SurveyTemplateController extends Controller
{
    public function __construct(
        private readonly SurveyTemplateVersioningService $versioning
    ) {}

    public function index(Request $request): Response
    {
        $templates = SurveyTemplate::query()
            ->with('forkedFrom:id,title')
            ->withCount('sections')
            ->orderByDesc('id')
            ->paginate(20);

        return Inertia::render('Admin/SurveyTemplates/Index', [
            'templates' => $templates,
            'exitInterview' => $this->exitInterviewPanel($request),
        ]);
    }

    /**
     * @return array{
     *   sections: list<array{key: string, title: string, questions: list<array{key: string, body: string, hint?: string}>}>,
     *   consultantNoteFields: list<array{key: string, label: string}>,
     *   canManage: bool,
     *   needsCompanySelection: bool,
     *   companyPicker: list<array{id: int, name: string}>|null,
     *   activeCompany: array{id: int, name: string}|null,
     *   interviews: array{data: list<array<string, mixed>>}|null
     * }
     */
    private function exitInterviewPanel(Request $request): array
    {
        $user = Auth::user();
        $canManage = (bool) $user?->canAccessAdmin(AdminPermissionModule::Desligamento, PermissionAction::View);

        $panel = [
            'sections' => ExitInterviewScript::sections(),
            'consultantNoteFields' => ExitInterviewScript::consultantNoteFields(),
            'canManage' => $canManage,
            'needsCompanySelection' => false,
            'companyPicker' => null,
            'activeCompany' => null,
            'interviews' => null,
        ];

        if (! $canManage) {
            return $panel;
        }

        $context = app(DesligamentoCompanyContext::class);
        $panel['companyPicker'] = $context->availableCompanies()->all();

        if ($context->needsCompanySelection($request)) {
            $panel['needsCompanySelection'] = true;

            return $panel;
        }

        $company = $context->tryResolve($request);
        if (! $company) {
            $panel['needsCompanySelection'] = true;

            return $panel;
        }

        $panel['activeCompany'] = $company->only(['id', 'name']);
        $panel['interviews'] = [
            'data' => ExitInterview::query()
                ->where('company_id', $company->id)
                ->with(['employee:id,name,email'])
                ->orderByDesc('interview_date')
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->map(fn (ExitInterview $interview) => [
                    'id' => $interview->id,
                    'interview_date' => $interview->interview_date?->toDateString(),
                    'status' => $interview->status->value,
                    'status_label' => $interview->status->label(),
                    'employee' => $interview->employee?->only(['id', 'name', 'email']),
                ])
                ->values()
                ->all(),
        ];

        return $panel;
    }

    public function create(): Response
    {
        return Inertia::render('Admin/SurveyTemplates/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.title' => ['required', 'string', 'max:255'],
            'sections.*.description' => ['nullable', 'string'],
            'sections.*.questions' => ['required', 'array', 'min:1'],
            'sections.*.questions.*.body' => ['required', 'string'],
            'sections.*.questions.*.reverse_score' => ['boolean'],
            'sections.*.questions.*.weight' => ['nullable', 'numeric', 'min:0.01', 'max:100'],
            'sections.*.questions.*.response_scale' => ['nullable', 'string', 'in:frequency,agreement'],
        ]);

        DB::transaction(function () use ($data) {
            $template = SurveyTemplate::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'is_active' => true,
                'created_by' => Auth::id(),
            ]);

            foreach ($data['sections'] as $sIndex => $section) {
                $sec = SurveyTemplateSection::create([
                    'survey_template_id' => $template->id,
                    'title' => $section['title'],
                    'description' => $section['description'] ?? null,
                    'sort_order' => $sIndex,
                ]);

                foreach ($section['questions'] as $qIndex => $question) {
                    SurveyTemplateQuestion::create([
                        'survey_template_section_id' => $sec->id,
                        'body' => $question['body'],
                        'reverse_score' => $question['reverse_score'] ?? false,
                        'weight' => isset($question['weight']) ? (float) $question['weight'] : 1.0,
                        'response_scale' => $question['response_scale'] ?? 'frequency',
                        'sort_order' => $qIndex,
                    ]);
                }
            }
        });

        return redirect()->route('admin.survey-templates.index')->with('success', 'Mapeamento criado.');
    }

    public function show(SurveyTemplate $surveyTemplate): Response
    {
        $surveyTemplate->load(['sections.questions', 'forkedFrom:id,title']);

        return Inertia::render('Admin/SurveyTemplates/Show', [
            'template' => $surveyTemplate,
        ]);
    }

    public function edit(SurveyTemplate $surveyTemplate): Response
    {
        $surveyTemplate->load(['sections.questions', 'forkedFrom:id,title']);

        $surveysWithCompletedResponses = Survey::query()
            ->where('survey_template_id', $surveyTemplate->id)
            ->whereHas('completedResponses')
            ->count();

        return Inertia::render('Admin/SurveyTemplates/Edit', [
            'template' => $surveyTemplate,
            'surveysWithCompletedResponses' => $surveysWithCompletedResponses,
        ]);
    }

    public function update(Request $request, SurveyTemplate $surveyTemplate): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.id' => ['nullable', 'integer'],
            'sections.*.title' => ['required', 'string', 'max:255'],
            'sections.*.description' => ['nullable', 'string'],
            'sections.*.questions' => ['required', 'array', 'min:1'],
            'sections.*.questions.*.id' => ['nullable', 'integer'],
            'sections.*.questions.*.body' => ['required', 'string'],
            'sections.*.questions.*.reverse_score' => ['boolean'],
            'sections.*.questions.*.weight' => ['nullable', 'numeric', 'min:0.01', 'max:100'],
            'sections.*.questions.*.response_scale' => ['nullable', 'string', 'in:frequency,agreement'],
        ]);

        if ($this->versioning->shouldFork($surveyTemplate)) {
            $fork = $this->versioning->createFork($surveyTemplate, $data);

            return redirect()
                ->route('admin.survey-templates.edit', $fork)
                ->with(
                    'success',
                    'Nova versão do mapeamento criada. O mapeamento original (#'.$surveyTemplate->id.') e todas as respostas foram preservados.'
                );
        }

        DB::transaction(function () use ($surveyTemplate, $data) {
            $surveyTemplate->update([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $keptSectionIds = [];

            foreach ($data['sections'] as $sIndex => $section) {
                $sec = $this->resolveSection($surveyTemplate, $section);
                $sec->update([
                    'title' => $section['title'],
                    'description' => $section['description'] ?? null,
                    'sort_order' => $sIndex,
                ]);
                $keptSectionIds[] = $sec->id;

                $keptQuestionIds = [];
                foreach ($section['questions'] as $qIndex => $question) {
                    $q = $this->resolveQuestion($sec, $question);
                    $q->update([
                        'body' => $question['body'],
                        'reverse_score' => $question['reverse_score'] ?? false,
                        'weight' => isset($question['weight']) ? (float) $question['weight'] : 1.0,
                        'response_scale' => $question['response_scale'] ?? 'frequency',
                        'sort_order' => $qIndex,
                    ]);
                    $keptQuestionIds[] = $q->id;
                }

                $sec->questions()->whereNotIn('id', $keptQuestionIds)->delete();
            }

            $surveyTemplate->sections()->whereNotIn('id', $keptSectionIds)->delete();
        });

        return redirect()->route('admin.survey-templates.index')->with('success', 'Mapeamento atualizado.');
    }

    public function destroy(SurveyTemplate $surveyTemplate): RedirectResponse
    {
        if ($surveyTemplate->surveys()->exists()) {
            return back()->with(
                'error',
                'Não é possível excluir: há pesquisas vinculadas a este mapeamento. O histórico de respostas deve ser preservado.'
            );
        }

        if ($surveyTemplate->forks()->exists()) {
            return back()->with(
                'error',
                'Não é possível excluir: existem versões derivadas deste mapeamento.'
            );
        }

        $surveyTemplate->delete();

        return redirect()->route('admin.survey-templates.index')->with('success', 'Mapeamento removido.');
    }

    /**
     * @param  array<string, mixed>  $section
     */
    private function resolveSection(SurveyTemplate $template, array $section): SurveyTemplateSection
    {
        if (! empty($section['id'])) {
            $existing = SurveyTemplateSection::query()
                ->where('id', $section['id'])
                ->where('survey_template_id', $template->id)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return SurveyTemplateSection::create([
            'survey_template_id' => $template->id,
            'title' => $section['title'],
            'description' => $section['description'] ?? null,
            'sort_order' => 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $question
     */
    private function resolveQuestion(SurveyTemplateSection $section, array $question): SurveyTemplateQuestion
    {
        if (! empty($question['id'])) {
            $existing = SurveyTemplateQuestion::query()
                ->where('id', $question['id'])
                ->where('survey_template_section_id', $section->id)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return SurveyTemplateQuestion::create([
            'survey_template_section_id' => $section->id,
            'body' => $question['body'],
            'reverse_score' => $question['reverse_score'] ?? false,
            'weight' => isset($question['weight']) ? (float) $question['weight'] : 1.0,
            'response_scale' => $question['response_scale'] ?? 'frequency',
            'sort_order' => 0,
        ]);
    }
}
