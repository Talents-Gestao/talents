<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\Feedback;

use App\Actions\Feedback\DeleteFeedbackTemplate;
use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Http\Controllers\Concerns\ResolvesFeedbackRoutes;
use App\Models\Company;
use App\Models\FeedbackAuditLog;
use App\Models\FeedbackSession;
use App\Models\FeedbackTemplate;
use App\Models\FeedbackTemplateVersion;
use App\Services\Feedback\FeedbackTemplateVersioning;
use App\Support\Feedback\FeedbackCompanyContext;
use App\Support\Feedback\FeedbackVisibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Modelos de feedback. Há dois tipos:
 * - universais (company_id nulo): criados pela Talents no admin, valem para todas as empresas. Só as rotas
 *   do admin criam, editam, arquivam e excluem; no portal aparecem como «Modelos da Talents» (usar e duplicar).
 * - da empresa: criados pelo RH no portal, só aparecem para a empresa dona (no admin, para a empresa
 *   selecionada, com o selo «Desta empresa», e a Talents também os edita).
 * Criar ou duplicar no admin gera universal; no portal, gera da empresa. Só o perfil administrador gerencia.
 *
 * Versões (FeedbackTemplateVersioning): editar a estrutura de um modelo cuja versão atual já foi usada cria a
 * versão seguinte; os feedbacks feitos continuam na versão deles. Arquivar tira o modelo de «Novo feedback» sem
 * afetar os feedbacks existentes. Exclusão definitiva só para modelo nunca usado em nenhuma versão; nenhum
 * feedback é apagado junto com um modelo.
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

    public function __construct(private readonly FeedbackTemplateVersioning $versioning) {}

    public function index(Request $request): Response
    {
        $company = $this->companyForAdmin($request);
        $isAdmin = $this->isAdminContext($request);

        $templates = FeedbackTemplate::query()
            ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $company->id))
            ->withCount('sections')
            ->with('currentVersion:id,number')
            ->orderByRaw('company_id is null')
            ->orderBy('title')
            ->get();

        $templateIds = $templates->pluck('id');

        // Uso na empresa (portal e admin) e, só no admin, o total geral dos universais.
        $companyUsage = FeedbackSession::query()
            ->where('company_id', $company->id)
            ->whereIn('feedback_template_id', $templateIds)
            ->selectRaw('feedback_template_id, count(*) as total')
            ->groupBy('feedback_template_id')
            ->pluck('total', 'feedback_template_id');

        // Uso geral (todas as empresas, todas as versões): decide se a exclusão definitiva é possível.
        $totalUsage = FeedbackSession::query()
            ->whereIn('feedback_template_id', $templateIds)
            ->selectRaw('feedback_template_id, count(*) as total')
            ->groupBy('feedback_template_id')
            ->pluck('total', 'feedback_template_id');

        return Inertia::render('Client/Feedbacks/Templates/Index', [
            'templates' => $templates->map(function (FeedbackTemplate $template) use ($isAdmin, $companyUsage, $totalUsage) {
                $universal = $template->isGlobal();
                $inCompany = (int) ($companyUsage[$template->id] ?? 0);
                $total = (int) ($totalUsage[$template->id] ?? 0);
                $adminTotal = $isAdmin && $universal ? $total : null;

                return [
                    'id' => $template->id,
                    'title' => $template->title,
                    'description' => $template->description,
                    'is_archived' => $template->isArchived(),
                    'archived_at' => $template->archived_at?->toIso8601String(),
                    'is_own' => ! $universal,
                    'is_universal' => $universal,
                    // Portal só gerencia os da empresa; o admin gerencia universais e os da empresa selecionada.
                    'manageable' => $isAdmin || ! $universal,
                    'version_number' => (int) ($template->currentVersion?->number ?? 1),
                    'sections_count' => (int) $template->sections_count,
                    'sessions_count' => $adminTotal ?? $inCompany,
                    'sessions_count_company' => $inCompany,
                    'sessions_count_total' => $adminTotal,
                    // Exclusão definitiva só para modelo nunca usado (em nenhuma empresa e nenhuma versão).
                    'deletable' => $total === 0,
                ];
            })->values(),
            'permissions' => $this->permissions($request),
            'isAdminContext' => $isAdmin,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->companyForAdmin($request);

        return Inertia::render('Client/Feedbacks/Templates/Form', [
            'template' => null,
            'version' => null,
            'versions' => [],
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

        // No admin, todo modelo novo é universal (vale para todas as empresas), mesmo com empresa selecionada.
        $companyId = $this->isAdminContext($request) ? null : $company->id;

        DB::transaction(function () use ($companyId, $data, $request) {
            $template = FeedbackTemplate::query()->create([
                'company_id' => $companyId,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'is_default' => false,
                'is_active' => true,
                'created_by' => $request->user()->id,
            ]);

            $this->versioning->saveStructure($template, $data['sections'], $request->user());
        });

        return $this->feedbackRedirect('templates.index', [], 'Modelo de feedback criado.');
    }

    public function edit(Request $request, FeedbackTemplate $template): Response
    {
        $this->authorizeManageableTemplate($request, $template);
        $current = $this->versioning->currentVersion($template);
        $permissions = $this->permissions($request);
        $sessionsCount = $this->versioning->sessionsCount($current);
        $used = $current->isUsed();

        return Inertia::render('Client/Feedbacks/Templates/Form', [
            'template' => [
                'id' => $template->id,
                'title' => $template->title,
                'description' => $template->description,
                'is_universal' => $template->isGlobal(),
                'is_archived' => $template->isArchived(),
                'sections' => $this->versioning->canonical($current),
            ],
            // Versão atual já usada: salvar mudanças de estrutura cria a próxima versão (os feedbacks feitos não mudam).
            'version' => [
                'number' => $current->number,
                'sessions_count' => $sessionsCount,
                'used' => $used,
                'next_number' => $used ? (int) $template->versions()->max('number') + 1 : null,
            ],
            'versions' => $this->versionHistory($template),
            // Admin da Talents só com «ver» no módulo: abre em modo leitura, sem salvar.
            'readOnly' => ! $permissions['edit'],
            'permissions' => $permissions,
            'questionTypes' => $this->questionTypeOptions(),
            'audiences' => $this->audienceOptions(),
        ]);
    }

    public function update(Request $request, FeedbackTemplate $template): RedirectResponse
    {
        $this->authorizeManageableTemplate($request, $template);
        $data = $this->validated($request, requireStructure: false);

        $result = DB::transaction(function () use ($template, $data, $request) {
            $template->update([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
            ]);
            $this->versioning->syncMetadata($template);

            return array_key_exists('sections', $data)
                ? $this->versioning->saveStructure($template->fresh(), $data['sections'], $request->user())
                : null;
        });

        $message = match ($result['outcome'] ?? 'unchanged') {
            'new_version' => "Modelo atualizado: criada a versão {$result['version']->number}. Os feedbacks já feitos continuam na versão {$result['previous']}.",
            default => 'Modelo de feedback atualizado.',
        };

        return $this->feedbackRedirect('templates.index', [], $message);
    }

    /** Tira o modelo de «Novo feedback»; os feedbacks já feitos seguem normais. */
    public function archive(Request $request, FeedbackTemplate $template): RedirectResponse
    {
        $this->authorizeManageableTemplate($request, $template);
        abort_unless($this->permissions($request)['edit'], 403);

        if (! $template->isArchived()) {
            $template->update(['archived_at' => now(), 'archived_by_user_id' => $request->user()->id, 'is_active' => false]);
            $this->audit($request, $template, FeedbackAuditLog::ACTION_TEMPLATE_ARCHIVED);
        }

        return $this->feedbackRedirect('templates.index', [], 'Modelo arquivado. Ele não aparece mais em «Novo feedback»; os feedbacks já feitos continuam normais.');
    }

    public function unarchive(Request $request, FeedbackTemplate $template): RedirectResponse
    {
        $this->authorizeManageableTemplate($request, $template);
        abort_unless($this->permissions($request)['edit'], 403);

        if ($template->isArchived()) {
            $template->update(['archived_at' => null, 'archived_by_user_id' => null, 'is_active' => true]);
            $this->audit($request, $template, FeedbackAuditLog::ACTION_TEMPLATE_UNARCHIVED);
        }

        return $this->feedbackRedirect('templates.index', [], 'Modelo reativado. Ele volta a aparecer em «Novo feedback».');
    }

    /**
     * Cópia editável de um modelo universal ou da empresa (estrutura da versão atual, chaves preservadas).
     * No admin a cópia é universal; no portal, pertence à empresa de quem duplica.
     */
    public function duplicate(Request $request, FeedbackTemplate $template): RedirectResponse
    {
        $company = $this->companyForAdmin($request);
        abort_unless($template->company_id === null || (int) $template->company_id === (int) $company->id, 404);

        $companyId = $this->isAdminContext($request) ? null : $company->id;
        $structure = $this->versioning->canonical($this->versioning->currentVersion($template));

        $copy = DB::transaction(function () use ($companyId, $template, $request, $structure) {
            $copy = FeedbackTemplate::query()->create([
                'company_id' => $companyId,
                'title' => $this->copyTitle($template->title),
                'description' => $template->description,
                'is_default' => false,
                'is_active' => true,
                'created_by' => $request->user()->id,
            ]);

            $version = $this->versioning->currentVersion($copy);
            $this->versioning->writeRows($version, $structure);
            $this->versioning->refreshSnapshot($version);

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

    /**
     * Situação da exclusão para o alerta da tela: modelo usado em qualquer versão não é excluído (arquiva-se).
     */
    public function deletionImpact(Request $request, FeedbackTemplate $template, DeleteFeedbackTemplate $delete): JsonResponse
    {
        $this->authorizeManageableTemplate($request, $template);
        abort_unless($this->permissions($request)['delete'], 403);

        $sessions = $delete->usage($template);

        return response()->json([
            'id' => $template->id,
            'title' => $template->title,
            'sessions_count' => $sessions,
            'blocked' => $sessions > 0,
            'blocked_message' => $sessions > 0 ? DeleteFeedbackTemplate::IN_USE_MESSAGE : null,
        ]);
    }

    /** Exclusão definitiva: só modelo nunca usado. Nenhum feedback é apagado junto. */
    public function destroy(Request $request, FeedbackTemplate $template, DeleteFeedbackTemplate $delete): RedirectResponse
    {
        $this->authorizeManageableTemplate($request, $template);

        $delete->execute($template, $request->user());

        return $this->feedbackRedirect('templates.index', [], 'Modelo de feedback excluído.');
    }

    private function companyForAdmin(Request $request): Company
    {
        abort_unless(FeedbackVisibility::actsAsCompanyAdmin($request->user()), 403, 'Apenas administradores gerenciam modelos de feedback.');

        return $this->company($request);
    }

    /**
     * Quem edita, arquiva e exclui: no admin, universais e os da empresa selecionada; no portal, só os da
     * própria empresa. Modelo de outra empresa (ou universal, no portal) não existe para quem pede: 404.
     */
    private function authorizeManageableTemplate(Request $request, FeedbackTemplate $template): void
    {
        $company = $this->companyForAdmin($request);

        if ($template->isGlobal()) {
            abort_unless($this->isAdminContext($request), 404);

            return;
        }

        abort_unless((int) $template->company_id === (int) $company->id, 404);
    }

    private function isAdminContext(Request $request): bool
    {
        return app(FeedbackCompanyContext::class)->isAdminContext($request);
    }

    private function audit(Request $request, FeedbackTemplate $template, string $action): void
    {
        FeedbackAuditLog::query()->create([
            'company_id' => $template->company_id,
            'user_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => FeedbackAuditLog::SUBJECT_TEMPLATE,
            'subject_id' => $template->id,
            'subject_label' => $template->title,
        ]);
    }

    /**
     * Histórico de versões: número, data, autor e quantos feedbacks usam cada uma.
     *
     * @return list<array{number: int, created_at: string|null, created_by: string|null, sessions_count: int, is_current: bool}>
     */
    private function versionHistory(FeedbackTemplate $template): array
    {
        $usage = FeedbackSession::query()
            ->where('feedback_template_id', $template->id)
            ->selectRaw('feedback_template_version_id, count(*) as total')
            ->groupBy('feedback_template_version_id')
            ->pluck('total', 'feedback_template_version_id');

        return $template->versions()->with('creator:id,name')->get()
            ->sortByDesc('number')
            ->map(fn (FeedbackTemplateVersion $v) => [
                'number' => $v->number,
                'created_at' => $v->created_at?->toIso8601String(),
                'created_by' => $v->creator?->name,
                'sessions_count' => (int) ($usage[$v->id] ?? 0),
                'is_current' => (int) $v->id === (int) $template->current_version_id,
            ])
            ->values()
            ->all();
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

        if (! $this->isAdminContext($request)) {
            return ['create' => true, 'edit' => true, 'delete' => true];
        }

        return [
            'create' => $user->canAccessAdmin(AdminPermissionModule::Feedbacks, PermissionAction::Create),
            'edit' => $user->canAccessAdmin(AdminPermissionModule::Feedbacks, PermissionAction::Edit),
            'delete' => $user->canAccessAdmin(AdminPermissionModule::Feedbacks, PermissionAction::Delete),
        ];
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

        $keyRegex = 'regex:'.FeedbackTemplateVersioning::KEY_PATTERN;

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            // Na edição, sem «sections» só nome e descrição mudam.
            'sections' => $requireStructure ? ['required', 'array', 'min:1', 'max:20'] : ['sometimes', 'array', 'min:1', 'max:20'],
            'sections.*.key' => ['nullable', 'string', $keyRegex],
            'sections.*.section_type' => ['nullable', Rule::in(self::SECTION_TYPES)],
            'sections.*.audience' => ['nullable', Rule::in(self::AUDIENCES)],
            'sections.*.title' => ['required', 'string', 'max:255'],
            'sections.*.description' => ['nullable', 'string', 'max:2000'],
            'sections.*.questions' => ['nullable', 'array', 'max:40'],
            'sections.*.questions.*.key' => ['nullable', 'string', $keyRegex],
            'sections.*.questions.*.body' => ['required', 'string', 'max:2000'],
            'sections.*.questions.*.question_type' => ['required', Rule::in(self::QUESTION_TYPES)],
            'sections.*.questions.*.is_required' => ['sometimes', 'boolean'],
            'sections.*.questions.*.options' => ['nullable', 'array', 'max:20'],
            // Opção em branco é descartada ao gravar; o mínimo de duas é checado abaixo.
            'sections.*.questions.*.options.*.label' => ['nullable', 'string', 'max:255'],
            'sections.*.questions.*.options.*.value' => ['nullable', 'string', $keyRegex],
        ], [
            'title.required' => 'Informe o nome do modelo.',
            'title.max' => 'O nome do modelo pode ter até 255 caracteres.',
            'description.max' => 'A descrição pode ter até 2.000 caracteres.',
            'sections.required' => 'O modelo precisa de pelo menos uma seção com perguntas.',
            'sections.min' => 'O modelo precisa de pelo menos uma seção com perguntas.',
            'sections.max' => 'O modelo pode ter até 20 seções.',
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

        if (array_key_exists('sections', $data) && $sectionsWithQuestions === 0 && ! isset($errors['sections'])) {
            $errors['sections'] = 'O modelo precisa de pelo menos uma seção com perguntas.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
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
