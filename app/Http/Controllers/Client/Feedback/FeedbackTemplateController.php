<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\Feedback;

use App\Http\Controllers\Concerns\ResolvesFeedbackRoutes;
use App\Models\Company;
use App\Models\FeedbackSession;
use App\Models\FeedbackTemplate;
use App\Support\Feedback\FeedbackVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Modelos de feedback da própria empresa (portal; no admin, da empresa selecionada).
 * Só o perfil administrador gerencia; o modelo só aparece para a empresa dona. Os modelos da Talents
 * (company_id nulo) são listados como referência, sem edição. Depois que um modelo é usado em algum
 * feedback, a estrutura (seções e perguntas) fica travada: só nome, descrição e ativo mudam.
 */
class FeedbackTemplateController extends FeedbackCompanyController
{
    use ResolvesFeedbackRoutes;

    private const QUESTION_TYPES = ['textarea', 'text', 'single_choice', 'bullet_list'];

    public function index(Request $request): Response
    {
        $company = $this->companyForAdmin($request);

        $templates = FeedbackTemplate::query()
            ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $company->id))
            ->withCount('sections')
            ->orderByRaw('company_id is null')
            ->orderBy('title')
            ->get();

        $usage = FeedbackSession::query()
            ->where('company_id', $company->id)
            ->whereIn('feedback_template_id', $templates->pluck('id'))
            ->selectRaw('feedback_template_id, count(*) as total')
            ->groupBy('feedback_template_id')
            ->pluck('total', 'feedback_template_id');

        return Inertia::render('Client/Feedbacks/Templates/Index', [
            'templates' => $templates->map(fn (FeedbackTemplate $template) => [
                'id' => $template->id,
                'title' => $template->title,
                'description' => $template->description,
                'is_active' => (bool) $template->is_active,
                'is_own' => $template->company_id !== null,
                'sections_count' => (int) $template->sections_count,
                'sessions_count' => (int) ($usage[$template->id] ?? 0),
            ])->values(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->companyForAdmin($request);

        return Inertia::render('Client/Feedbacks/Templates/Form', [
            'template' => null,
            'structureLocked' => false,
            'questionTypes' => $this->questionTypeOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->companyForAdmin($request);
        $data = $this->validated($request);

        DB::transaction(function () use ($company, $data, $request) {
            $template = FeedbackTemplate::query()->create([
                'company_id' => $company->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'is_default' => false,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'created_by' => $request->user()->id,
            ]);

            $this->writeStructure($template, $data['sections'] ?? []);
        });

        return $this->feedbackRedirect('templates.index', [], 'Modelo de feedback criado.');
    }

    public function edit(Request $request, FeedbackTemplate $template): Response
    {
        $this->authorizeOwnTemplate($request, $template);
        $template->load('sections.questions');

        return Inertia::render('Client/Feedbacks/Templates/Form', [
            'template' => [
                'id' => $template->id,
                'title' => $template->title,
                'description' => $template->description,
                'is_active' => (bool) $template->is_active,
                'sections' => $template->sections->map(fn ($section) => [
                    'title' => $section->title,
                    'description' => $section->description,
                    'questions' => $section->questions->map(fn ($question) => [
                        'body' => $question->body,
                        'question_type' => $question->question_type,
                        'is_required' => (bool) $question->is_required,
                        'options' => collect($question->options ?? [])->pluck('label')->values()->all(),
                    ])->values(),
                ])->values(),
            ],
            'structureLocked' => $this->isInUse($template),
            'questionTypes' => $this->questionTypeOptions(),
        ]);
    }

    public function update(Request $request, FeedbackTemplate $template): RedirectResponse
    {
        $this->authorizeOwnTemplate($request, $template);
        $data = $this->validated($request);
        $locked = $this->isInUse($template);

        DB::transaction(function () use ($template, $data, $locked) {
            $template->update([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            if (! $locked && array_key_exists('sections', $data)) {
                $template->sections()->get()->each->delete();
                $this->writeStructure($template, $data['sections'] ?? []);
                $template->increment('version');
            }
        });

        return $this->feedbackRedirect(
            'templates.index',
            [],
            $locked
                ? 'Modelo atualizado. As perguntas não mudam porque o modelo já foi usado em feedbacks.'
                : 'Modelo de feedback atualizado.',
        );
    }

    public function destroy(Request $request, FeedbackTemplate $template): RedirectResponse
    {
        $this->authorizeOwnTemplate($request, $template);

        if ($this->isInUse($template)) {
            return redirect()
                ->route($this->feedbackRouteName('templates.index'))
                ->with('error', 'Este modelo já foi usado em feedbacks e não pode ser excluído. Desative-o.');
        }

        $template->delete();

        return $this->feedbackRedirect('templates.index', [], 'Modelo de feedback excluído.');
    }

    private function companyForAdmin(Request $request): Company
    {
        abort_unless(FeedbackVisibility::actsAsCompanyAdmin($request->user()), 403, 'Apenas administradores gerenciam modelos de feedback.');

        return $this->company($request);
    }

    private function authorizeOwnTemplate(Request $request, FeedbackTemplate $template): void
    {
        $company = $this->companyForAdmin($request);

        // Modelo de outra empresa ou da Talents: não existe para esta empresa.
        abort_unless($template->company_id !== null && (int) $template->company_id === (int) $company->id, 404);
    }

    private function isInUse(FeedbackTemplate $template): bool
    {
        return FeedbackSession::query()->where('feedback_template_id', $template->id)->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'sections' => ['sometimes', 'array', 'max:20'],
            'sections.*.title' => ['required', 'string', 'max:255'],
            'sections.*.description' => ['nullable', 'string', 'max:2000'],
            'sections.*.questions' => ['required', 'array', 'min:1', 'max:40'],
            'sections.*.questions.*.body' => ['required', 'string', 'max:2000'],
            'sections.*.questions.*.question_type' => ['required', Rule::in(self::QUESTION_TYPES)],
            'sections.*.questions.*.is_required' => ['sometimes', 'boolean'],
            'sections.*.questions.*.options' => ['nullable', 'array', 'max:20'],
            'sections.*.questions.*.options.*' => ['required', 'string', 'max:255'],
        ], [
            'title.required' => 'Informe o nome do modelo.',
            'sections.*.title.required' => 'Informe o título da seção.',
            'sections.*.questions.required' => 'Cada seção precisa de pelo menos uma pergunta.',
            'sections.*.questions.min' => 'Cada seção precisa de pelo menos uma pergunta.',
            'sections.*.questions.*.body.required' => 'Informe o texto da pergunta.',
        ]);

        foreach ($data['sections'] ?? [] as $sectionIndex => $section) {
            foreach ($section['questions'] ?? [] as $questionIndex => $question) {
                $options = array_filter(array_map('trim', $question['options'] ?? []));
                if ($question['question_type'] === 'single_choice' && count($options) < 2) {
                    throw ValidationException::withMessages([
                        "sections.{$sectionIndex}.questions.{$questionIndex}.options" => 'Informe pelo menos duas opções.',
                    ]);
                }
            }
        }

        return $data;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    private function writeStructure(FeedbackTemplate $template, array $sections): void
    {
        foreach (array_values($sections) as $sectionIndex => $sectionData) {
            $section = $template->sections()->create([
                'key' => 'section_'.($sectionIndex + 1),
                'title' => $sectionData['title'],
                'description' => $sectionData['description'] ?? null,
                'section_type' => 'questions',
                'audience' => 'both',
                'sort_order' => $sectionIndex + 1,
            ]);

            foreach (array_values($sectionData['questions'] ?? []) as $questionIndex => $questionData) {
                $type = $questionData['question_type'];
                $options = $type === 'single_choice'
                    ? collect($questionData['options'] ?? [])
                        ->map(fn ($label) => trim((string) $label))
                        ->filter()
                        ->values()
                        ->map(fn (string $label, int $i) => ['value' => 'option_'.($i + 1), 'label' => $label])
                        ->all()
                    : null;

                $section->questions()->create([
                    'key' => 'q_'.($sectionIndex + 1).'_'.($questionIndex + 1),
                    'body' => $questionData['body'],
                    'question_type' => $type,
                    'options' => $options ?: null,
                    'is_required' => (bool) ($questionData['is_required'] ?? false),
                    'sort_order' => $questionIndex + 1,
                ]);
            }
        }
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function questionTypeOptions(): array
    {
        return [
            ['value' => 'textarea', 'label' => 'Texto longo'],
            ['value' => 'text', 'label' => 'Texto curto'],
            ['value' => 'single_choice', 'label' => 'Escolha única'],
            ['value' => 'bullet_list', 'label' => 'Lista de itens'],
        ];
    }
}
