<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\Feedback;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Http\Controllers\Concerns\ResolvesFeedbackRoutes;
use App\Models\Company;
use App\Models\FeedbackSession;
use App\Models\FeedbackTemplate;
use App\Models\FeedbackTemplateQuestion;
use App\Models\FeedbackTemplateSection;
use App\Support\Feedback\FeedbackCompanyContext;
use App\Support\Feedback\FeedbackVisibility;
use BackedEnum;
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
 * (company_id nulo) são listados como referência, sem edição, mas podem ser duplicados para a empresa.
 * Depois que um modelo é usado em algum feedback, a estrutura (seções e perguntas) fica travada: só nome,
 * descrição e ativo mudam; para mudar as perguntas, duplica-se o modelo.
 *
 * A estrutura faz ida e volta fiel: chave da seção/pergunta, tipo de seção (perguntas ou orientação),
 * público (inclusive «leader_self», visível só a administradores) e valor das opções são preservados,
 * porque o painel da equipe (termômetro, 9-box) lê respostas por chave e valor.
 */
class FeedbackTemplateController extends FeedbackCompanyController
{
    use ResolvesFeedbackRoutes;

    private const QUESTION_TYPES = ['textarea', 'text', 'single_choice', 'bullet_list', 'ccm_block', 'action_table'];

    private const SECTION_TYPES = ['questions', 'intro'];

    private const AUDIENCES = ['both', 'employee', 'leader', FeedbackVisibility::AUDIENCE_LEADER_SELF];

    private const KEY_PATTERN = '/^[a-z0-9_]{1,64}$/';

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
            'permissions' => $this->permissions($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->companyForAdmin($request);

        return Inertia::render('Client/Feedbacks/Templates/Form', [
            'template' => null,
            'structureLocked' => false,
            'sessionsCount' => 0,
            'readOnly' => false,
            'permissions' => $this->permissions($request),
            'questionTypes' => $this->questionTypeOptions(),
            'audiences' => $this->audienceOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->companyForAdmin($request);
        $data = $this->validated($request, requireStructure: true);

        DB::transaction(function () use ($company, $data, $request) {
            $template = FeedbackTemplate::query()->create([
                'company_id' => $company->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'is_default' => false,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'created_by' => $request->user()->id,
            ]);

            $this->writeStructure($template, $data['sections']);
        });

        return $this->feedbackRedirect('templates.index', [], 'Modelo de feedback criado.');
    }

    public function edit(Request $request, FeedbackTemplate $template): Response
    {
        $this->authorizeOwnTemplate($request, $template);
        $sessionsCount = $this->usageCount($template);
        $permissions = $this->permissions($request);

        return Inertia::render('Client/Feedbacks/Templates/Form', [
            'template' => [
                'id' => $template->id,
                'title' => $template->title,
                'description' => $template->description,
                'is_active' => (bool) $template->is_active,
                'sections' => $this->structureOf($template),
            ],
            // As respostas apontam para as perguntas do modelo; por isso a estrutura trava após o primeiro uso.
            'structureLocked' => $sessionsCount > 0,
            'sessionsCount' => $sessionsCount,
            // Admin da Talents só com «ver» no módulo: abre em modo leitura, sem salvar.
            'readOnly' => ! $permissions['edit'],
            'permissions' => $permissions,
            'questionTypes' => $this->questionTypeOptions(),
            'audiences' => $this->audienceOptions(),
        ]);
    }

    public function update(Request $request, FeedbackTemplate $template): RedirectResponse
    {
        $this->authorizeOwnTemplate($request, $template);
        $locked = $this->isInUse($template);
        $data = $this->validated($request, requireStructure: ! $locked);

        DB::transaction(function () use ($template, $data, $locked) {
            $template->update([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            if (! $locked) {
                $template->sections()->get()->each->delete();
                $this->writeStructure($template, $data['sections']);
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

    /**
     * Cópia editável (sem trava) de um modelo da empresa ou da Talents, pertencente à empresa de quem duplica.
     */
    public function duplicate(Request $request, FeedbackTemplate $template): RedirectResponse
    {
        $company = $this->companyForAdmin($request);
        abort_unless($template->company_id === null || (int) $template->company_id === (int) $company->id, 404);

        $copy = DB::transaction(function () use ($company, $template, $request) {
            $copy = FeedbackTemplate::query()->create([
                'company_id' => $company->id,
                'title' => $this->copyTitle($template->title),
                'description' => $template->description,
                'is_default' => false,
                'is_active' => true,
                'created_by' => $request->user()->id,
            ]);

            $this->writeStructure($copy, $this->structureOf($template));

            return $copy;
        });

        return $this->feedbackRedirect('templates.edit', $copy, 'Cópia criada. Ajuste o que precisar e salve.');
    }

    /**
     * «Nome (cópia)» com no máximo 255 caracteres (contagem por caractere, não por byte).
     */
    private function copyTitle(string $title): string
    {
        $suffix = ' (cópia)';

        return mb_substr($title, 0, 255 - mb_strlen($suffix, 'UTF-8'), 'UTF-8').$suffix;
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

    /**
     * Ações que a tela oferece. No portal o administrador da empresa pode tudo; no admin da Talents vale a
     * matriz do módulo «feedbacks» (as rotas já recusam pelo método HTTP; isto só ajusta a interface).
     *
     * @return array{create: bool, edit: bool, delete: bool}
     */
    private function permissions(Request $request): array
    {
        $user = $request->user();

        if (! app(FeedbackCompanyContext::class)->isAdminContext($request)) {
            return ['create' => true, 'edit' => true, 'delete' => true];
        }

        return [
            'create' => $user->canAccessAdmin(AdminPermissionModule::Feedbacks, PermissionAction::Create),
            'edit' => $user->canAccessAdmin(AdminPermissionModule::Feedbacks, PermissionAction::Edit),
            'delete' => $user->canAccessAdmin(AdminPermissionModule::Feedbacks, PermissionAction::Delete),
        ];
    }

    private function isInUse(FeedbackTemplate $template): bool
    {
        return FeedbackSession::query()->where('feedback_template_id', $template->id)->exists();
    }

    private function usageCount(FeedbackTemplate $template): int
    {
        return FeedbackSession::query()->where('feedback_template_id', $template->id)->count();
    }

    /**
     * Estrutura no formato do formulário (e da gravação).
     *
     * @return list<array<string, mixed>>
     */
    private function structureOf(FeedbackTemplate $template): array
    {
        $template->loadMissing('sections.questions');

        return $template->sections->map(fn (FeedbackTemplateSection $section) => [
            'key' => $section->key,
            'section_type' => $this->raw($section->section_type) ?: 'questions',
            'audience' => $this->raw($section->audience) ?: 'both',
            'title' => $section->title,
            'description' => $section->description ?? '',
            'questions' => $section->questions->map(fn (FeedbackTemplateQuestion $question) => [
                'key' => $question->key,
                'body' => $question->body,
                'question_type' => $this->raw($question->question_type),
                'is_required' => (bool) $question->is_required,
                'options' => collect($question->options ?? [])
                    ->map(fn ($option) => [
                        'value' => (string) ($option['value'] ?? ''),
                        'label' => (string) ($option['label'] ?? ''),
                    ])
                    ->values()
                    ->all(),
            ])->values()->all(),
        ])->values()->all();
    }

    private function raw(mixed $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $requireStructure): array
    {
        // Opções aceitam texto simples ("Bom") ou objeto {value, label}; normaliza para objeto.
        $sections = $request->input('sections');
        if (is_array($sections)) {
            foreach ($sections as $s => $section) {
                foreach ((is_array($section['questions'] ?? null) ? $section['questions'] : []) as $q => $question) {
                    if (! is_array($question['options'] ?? null)) {
                        continue;
                    }
                    $sections[$s]['questions'][$q]['options'] = array_map(
                        fn ($option) => is_array($option) ? $option : ['value' => null, 'label' => $option],
                        $question['options'],
                    );
                }
            }
            $request->merge(['sections' => $sections]);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'sections' => $requireStructure ? ['required', 'array', 'min:1', 'max:20'] : ['prohibited'],
            'sections.*.key' => ['nullable', 'string', 'regex:'.self::KEY_PATTERN],
            'sections.*.section_type' => ['nullable', Rule::in(self::SECTION_TYPES)],
            'sections.*.audience' => ['nullable', Rule::in(self::AUDIENCES)],
            'sections.*.title' => ['required', 'string', 'max:255'],
            'sections.*.description' => ['nullable', 'string', 'max:2000'],
            'sections.*.questions' => ['nullable', 'array', 'max:40'],
            'sections.*.questions.*.key' => ['nullable', 'string', 'regex:'.self::KEY_PATTERN],
            'sections.*.questions.*.body' => ['required', 'string', 'max:2000'],
            'sections.*.questions.*.question_type' => ['required', Rule::in(self::QUESTION_TYPES)],
            'sections.*.questions.*.is_required' => ['sometimes', 'boolean'],
            'sections.*.questions.*.options' => ['nullable', 'array', 'max:20'],
            // Opção em branco é descartada ao gravar (writeStructure); o mínimo de duas é checado abaixo.
            'sections.*.questions.*.options.*.label' => ['nullable', 'string', 'max:255'],
            'sections.*.questions.*.options.*.value' => ['nullable', 'string', 'regex:'.self::KEY_PATTERN],
        ], [
            'title.required' => 'Informe o nome do modelo.',
            'title.max' => 'O nome do modelo pode ter até 255 caracteres.',
            'description.max' => 'A descrição pode ter até 2.000 caracteres.',
            'sections.required' => 'O modelo precisa de pelo menos uma seção com perguntas.',
            'sections.min' => 'O modelo precisa de pelo menos uma seção com perguntas.',
            'sections.max' => 'O modelo pode ter até 20 seções.',
            'sections.prohibited' => 'Este modelo já foi usado em feedbacks: as seções e perguntas não podem mais ser alteradas.',
            'sections.*.key.regex' => 'Identificador da seção inválido.',
            'sections.*.section_type.in' => 'Tipo de seção inválido.',
            'sections.*.audience.in' => 'Público da seção inválido.',
            'sections.*.title.required' => 'Informe o título da seção.',
            'sections.*.title.max' => 'O título da seção pode ter até 255 caracteres.',
            'sections.*.description.max' => 'A orientação da seção pode ter até 2.000 caracteres.',
            'sections.*.questions.max' => 'Cada seção pode ter até 40 perguntas.',
            'sections.*.questions.*.key.regex' => 'Identificador da pergunta inválido.',
            'sections.*.questions.*.body.required' => 'Informe o texto da pergunta.',
            'sections.*.questions.*.body.max' => 'O texto da pergunta pode ter até 2.000 caracteres.',
            'sections.*.questions.*.question_type.required' => 'Escolha o tipo de resposta.',
            'sections.*.questions.*.question_type.in' => 'Tipo de resposta inválido.',
            'sections.*.questions.*.options.max' => 'Uma pergunta pode ter até 20 opções.',
            'sections.*.questions.*.options.*.label.max' => 'Cada opção pode ter até 255 caracteres.',
            'sections.*.questions.*.options.*.value.regex' => 'Identificador da opção inválido.',
        ]);

        $errors = [];
        $sectionsWithQuestions = 0;

        foreach ($data['sections'] ?? [] as $sectionIndex => $section) {
            if (($section['section_type'] ?? 'questions') === 'intro') {
                continue;
            }

            if (empty($section['questions'])) {
                $errors["sections.{$sectionIndex}.questions"] = 'Cada seção precisa de pelo menos uma pergunta.';

                continue;
            }
            $sectionsWithQuestions++;

            foreach ($section['questions'] as $questionIndex => $question) {
                $labels = array_filter(array_map(
                    fn ($option) => trim((string) ($option['label'] ?? '')),
                    $question['options'] ?? [],
                ));
                if ($question['question_type'] === 'single_choice' && count($labels) < 2) {
                    $errors["sections.{$sectionIndex}.questions.{$questionIndex}.options"] = 'Informe pelo menos duas opções.';
                }
            }
        }

        if ($requireStructure && $sectionsWithQuestions === 0 && ! isset($errors['sections'])) {
            $errors['sections'] = 'O modelo precisa de pelo menos uma seção com perguntas.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    private function writeStructure(FeedbackTemplate $template, array $sections): void
    {
        $sectionKeys = [];
        $questionKeys = [];

        foreach (array_values($sections) as $sectionIndex => $sectionData) {
            $sectionType = ($sectionData['section_type'] ?? 'questions') === 'intro' ? 'intro' : 'questions';

            $section = $template->sections()->create([
                'key' => $this->uniqueKey($sectionData['key'] ?? null, 'section_'.($sectionIndex + 1), $sectionKeys),
                'title' => $sectionData['title'],
                'description' => ($sectionData['description'] ?? '') !== '' ? $sectionData['description'] : null,
                'section_type' => $sectionType,
                'audience' => $sectionData['audience'] ?? 'both',
                'sort_order' => $sectionIndex + 1,
            ]);

            if ($sectionType === 'intro') {
                continue;
            }

            foreach (array_values($sectionData['questions'] ?? []) as $questionIndex => $questionData) {
                $type = $questionData['question_type'];

                $section->questions()->create([
                    'key' => $this->uniqueKey(
                        $questionData['key'] ?? null,
                        'q_'.($sectionIndex + 1).'_'.($questionIndex + 1),
                        $questionKeys,
                    ),
                    'body' => $questionData['body'],
                    'question_type' => $type,
                    'options' => $type === 'single_choice' ? $this->normalizeOptions($questionData['options'] ?? []) : null,
                    'is_required' => (bool) ($questionData['is_required'] ?? false),
                    'sort_order' => $questionIndex + 1,
                ]);
            }
        }
    }

    /**
     * Mantém o valor de cada opção (o painel da equipe lê por valor); opção nova recebe option_N livre.
     *
     * @param  array<int, mixed>  $options
     * @return list<array{value: string, label: string}>|null
     */
    private function normalizeOptions(array $options): ?array
    {
        $used = [];
        $result = [];

        foreach ($options as $option) {
            $label = trim((string) (is_array($option) ? ($option['label'] ?? '') : $option));
            if ($label === '') {
                continue;
            }
            $value = is_array($option) ? ($option['value'] ?? null) : null;
            $result[] = ['value' => $this->uniqueKey($value, null, $used, 'option_'), 'label' => $label];
        }

        return $result ?: null;
    }

    /**
     * @param  array<string, true>  $used  chaves já usadas (atualizado por referência)
     */
    private function uniqueKey(?string $preferred, ?string $fallback, array &$used, string $prefix = 'k_'): string
    {
        foreach ([$preferred, $fallback] as $candidate) {
            if (is_string($candidate) && preg_match(self::KEY_PATTERN, $candidate) && ! isset($used[$candidate])) {
                return $this->markUsed($candidate, $used);
            }
        }

        $n = count($used) + 1;
        while (isset($used[$prefix.$n])) {
            $n++;
        }

        return $this->markUsed($prefix.$n, $used);
    }

    /**
     * @param  array<string, true>  $used
     */
    private function markUsed(string $key, array &$used): string
    {
        $used[$key] = true;

        return $key;
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
            ['value' => 'ccm_block', 'label' => 'Começar, continuar, melhorar e parar'],
            ['value' => 'action_table', 'label' => 'Tabela de ações (ação, responsável, prazo)'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function audienceOptions(): array
    {
        return [
            ['value' => 'both', 'label' => 'Líder e colaborador'],
            ['value' => 'employee', 'label' => 'Colaborador'],
            ['value' => 'leader', 'label' => 'Líder'],
            ['value' => FeedbackVisibility::AUDIENCE_LEADER_SELF, 'label' => 'Reflexão do líder (só administradores veem)'],
        ];
    }
}
