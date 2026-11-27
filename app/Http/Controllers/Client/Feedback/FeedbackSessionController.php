<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\Feedback;

use App\Actions\Company\ResolveOrCreateCompanyEmployee;
use App\Actions\Feedback\DeleteFeedbackSession;
use App\Actions\Feedback\ReopenFeedbackSession;
use App\Actions\Feedback\SendFeedbackSignatureInvites;
use App\Enums\AdminPermissionModule;
use App\Enums\FeedbackSessionStatus;
use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Enums\WorkspaceType;
use App\Http\Controllers\Concerns\ResolvesFeedbackRoutes;
use App\Models\Company;
use App\Models\FeedbackSession;
use App\Models\FeedbackSessionAnswer;
use App\Models\FeedbackTemplate;
use App\Models\FeedbackTemplateQuestion;
use App\Models\FeedbackTemplateSection;
use App\Models\FeedbackTemplateVersion;
use App\Models\User;
use App\Services\Feedback\FeedbackPdfService;
use App\Services\Feedback\FeedbackSessionVersionUpdater;
use App\Services\Feedback\FeedbackTemplateVersioning;
use App\Support\Company\CompanyEmployeeDirectory;
use App\Support\Feedback\FeedbackCompanyContext;
use App\Support\Feedback\FeedbackEmployeeMergeAccess;
use App\Support\Feedback\FeedbackRecordingAccess;
use App\Support\Feedback\FeedbackSessionStructure;
use App\Support\Feedback\FeedbackVisibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class FeedbackSessionController extends FeedbackCompanyController
{
    use ResolvesFeedbackRoutes;

    public function index(Request $request): Response
    {
        $company = $this->company($request);

        $sessions = FeedbackVisibility::scopeSessions(
            FeedbackSession::query()->where('company_id', $company->id),
            $request->user(),
        )
            ->with(['employee', 'leader', 'template'])
            ->withCount('answers')
            ->orderByDesc('id')
            ->paginate(15)
            ->through(fn (FeedbackSession $session) => [
                'id' => $session->id,
                'title' => $session->title,
                'status' => $session->status->value,
                'status_label' => $session->status->label(),
                'scheduled_at' => $session->scheduled_at?->toIso8601String(),
                'employee' => $session->collaboratorPayload(),
                'leader' => $session->leader?->only(['id', 'name', 'email']),
                'template' => $session->template?->only(['id', 'title']),
                'answers_count' => (int) $session->answers_count,
            ]);

        return Inertia::render('Client/Feedbacks/Sessions/Index', [
            'sessions' => $sessions,
            'canDelete' => $this->canDelete($request),
            'canManageEmployees' => FeedbackEmployeeMergeAccess::canManage($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $company = $this->company($request);

        return Inertia::render('Client/Feedbacks/Sessions/Create', [
            'leaders' => $this->leaderOptions($company),
            'templates' => $this->availableTemplates($company),
            'employees' => app(CompanyEmployeeDirectory::class)->suggestionsFor($company),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->company($request);
        $data = $request->validate([
            'employee_name' => ['required', 'string', 'max:255'],
            'employee_email' => ['nullable', 'email', 'max:255'],
            // Modelo da Talents ou da própria empresa, e não arquivado; nunca é trocado em silêncio por outro.
            'feedback_template_id' => [
                'nullable',
                'integer',
                Rule::exists('feedback_templates', 'id')
                    ->whereNull('deleted_at')
                    ->whereNull('archived_at')
                    ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $company->id)),
            ],
            // Líder: acesso ativo à empresa do feedback (mesma fonte da lista de Acessos; isolamento entre empresas).
            'leader_user_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) use ($company): void {
                    if (! User::query()->withActiveCompanyAccess($company->id)->whereKey((int) $value)->exists()) {
                        $fail('O líder precisa ter acesso ativo ao sistema nesta empresa.');
                    }
                },
            ],
            'scheduled_at' => ['required', 'date'],
            'next_alignment_at' => ['nullable', 'date'],
            'title' => ['nullable', 'string', 'max:255'],
        ], [
            'feedback_template_id.exists' => 'Modelo indisponível para esta empresa (inexistente, excluído, de outra empresa ou arquivado). Escolha um modelo da lista.',
            'leader_user_id.required' => 'Selecione o líder responsável.',
        ], [
            'employee_name' => 'nome do colaborador',
            'employee_email' => 'e-mail do colaborador',
            'scheduled_at' => 'data do alinhamento',
            'feedback_template_id' => 'modelo',
            'leader_user_id' => 'líder responsável',
        ]);

        $employeeName = trim($data['employee_name']);
        $employeeEmail = isset($data['employee_email']) ? trim((string) $data['employee_email']) : '';
        $employeeEmail = $employeeEmail !== '' ? $employeeEmail : null;

        $template = isset($data['feedback_template_id'])
            ? FeedbackTemplate::query()->findOrFail($data['feedback_template_id'])
            : $this->availableTemplates($company)->first();

        if ($template === null) {
            Log::warning('Feedback: nenhum modelo ativo disponível ao criar feedback (rodar FeedbackTemplateSeeder?).', [
                'company_id' => $company->id,
                'user_id' => $request->user()->id,
            ]);

            throw ValidationException::withMessages([
                'feedback_template_id' => 'Nenhum modelo disponível. Fale com o suporte.',
            ]);
        }

        if ($request->user()->isCompanyUser() && ! $request->user()->isSuperAdmin()) {
            abort_unless((int) $data['leader_user_id'] === (int) $request->user()->id, 403);
        }

        $employee = app(ResolveOrCreateCompanyEmployee::class)->execute(
            $company,
            $employeeName,
            $employeeEmail,
            (int) $data['leader_user_id'],
        );

        $session = FeedbackSession::create([
            'company_id' => $company->id,
            'feedback_template_id' => $template->id,
            'company_employee_id' => $employee->id,
            'rhid_person_id' => null,
            'employee_name' => $employeeName,
            'employee_email' => $employeeEmail,
            'leader_user_id' => $data['leader_user_id'],
            'created_by_user_id' => $request->user()->id,
            'title' => $data['title'] ?? 'Feedback — '.$employeeName,
            'status' => FeedbackSessionStatus::InProgress,
            'scheduled_at' => $data['scheduled_at'],
            'next_alignment_at' => $data['next_alignment_at'] ?? null,
        ]);

        return $this->feedbackRedirect('sessions.edit', $session);
    }

    public function show(Request $request, FeedbackSession $session): Response
    {
        $company = $this->company($request);
        $this->authorizeSession($company, $session);

        return Inertia::render('Client/Feedbacks/Sessions/Show', [
            'session' => $this->sessionPayload($session, $request->user()),
            'canExportPdf' => FeedbackVisibility::actsAsCompanyAdmin($request->user()),
            'canDelete' => $this->canDelete($request),
            'recording' => FeedbackRecordingAccess::payload($recording = $session->currentRecording()),
            'recordingAccess' => FeedbackRecordingAccess::flags($request, $session),
            'recordingAi' => FeedbackRecordingAccess::aiPayload($recording),
        ]);
    }

    public function edit(Request $request, FeedbackSession $session): Response
    {
        $company = $this->company($request);
        $this->authorizeSession($company, $session);
        $this->abortUnlessEditable($session);

        return Inertia::render('Client/Feedbacks/Sessions/Edit', [
            'session' => $this->sessionPayload($session, $request->user()),
            'recording' => FeedbackRecordingAccess::payload($recording = $session->currentRecording()),
            'recordingAccess' => FeedbackRecordingAccess::flags($request, $session),
            'recordingAi' => FeedbackRecordingAccess::aiPayload($recording),
        ]);
    }

    public function update(Request $request, FeedbackSession $session): RedirectResponse
    {
        $company = $this->company($request);
        $this->authorizeSession($company, $session);
        $this->abortUnlessEditable($session);

        $data = $request->validate([
            'scheduled_at' => ['nullable', 'date'],
            'next_alignment_at' => ['nullable', 'date'],
            'answers' => ['nullable', 'array'],
            'section_extras' => ['nullable', 'array'],
            'section_extras.*.question' => ['nullable', 'string', 'max:500'],
            'section_extras.*.answer' => ['nullable', 'string', 'max:10000'],
            'submit_for_signature' => ['boolean'],
        ]);

        $session->update([
            'scheduled_at' => $data['scheduled_at'] ?? $session->scheduled_at,
            'next_alignment_at' => $data['next_alignment_at'] ?? $session->next_alignment_at,
            'status' => FeedbackSessionStatus::InProgress,
            'section_extras' => $this->normalizeSectionExtras(
                $session,
                $data['section_extras'] ?? [],
                $request->user(),
            ),
        ]);

        $this->syncAnswers($session, $data['answers'] ?? [], $request->user());

        if ($request->boolean('submit_for_signature')) {
            app(SendFeedbackSignatureInvites::class)->execute($session, $request->user());

            return $this->feedbackRedirect('sessions.show', $session, 'Convites de assinatura enviados por e-mail.');
        }

        return back()->with('success', 'Feedback salvo.');
    }

    public function sendSignatures(Request $request, FeedbackSession $session, SendFeedbackSignatureInvites $action): RedirectResponse
    {
        $company = $this->company($request);
        $this->authorizeSession($company, $session);
        abort_if($session->status === FeedbackSessionStatus::Completed, 403);

        $action->execute($session, $request->user());

        return $this->feedbackRedirect('sessions.show', $session, 'Convites de assinatura enviados por e-mail.');
    }

    /**
     * Prévia de «Atualizar para a versão X»: perguntas mantidas, novas e que saem (com resposta preservada).
     */
    public function versionPreview(Request $request, FeedbackSession $session, FeedbackSessionVersionUpdater $updater): JsonResponse
    {
        $company = $this->company($request);
        $this->authorizeSession($company, $session);
        abort_unless($session->isOpenForEditing(), 422, FeedbackSessionVersionUpdater::NOT_OPEN_MESSAGE);

        return response()->json($updater->preview($session, $this->latestVersion($session)));
    }

    /** Leva o feedback aberto para a versão atual do modelo, com confirmação explícita. */
    public function updateVersion(Request $request, FeedbackSession $session, FeedbackSessionVersionUpdater $updater): RedirectResponse
    {
        $company = $this->company($request);
        $this->authorizeSession($company, $session);

        $data = $request->validate([
            'confirmed' => ['accepted'],
            'version_id' => ['required', 'integer'],
        ], [
            'confirmed.accepted' => 'Confirme a atualização do feedback para a nova versão do modelo.',
        ]);

        $target = $this->latestVersion($session);
        if ((int) $data['version_id'] !== (int) $target->id) {
            throw ValidationException::withMessages([
                'version' => 'O modelo ganhou outra versão desde que você abriu a prévia. Confira de novo antes de atualizar.',
            ]);
        }

        $result = $updater->apply($session, $target, $request->user());

        $message = "Feedback atualizado para a versão {$result['to']} do modelo.";
        if ($result['answers_out_of_version'] > 0) {
            $message .= $result['answers_out_of_version'] === 1
                ? ' 1 resposta ficou fora da versão atual e continua guardada.'
                : " {$result['answers_out_of_version']} respostas ficaram fora da versão atual e continuam guardadas.";
        }

        return $this->feedbackRedirect('sessions.show', $session, $message);
    }

    /**
     * «Reabrir para edição»: cancela os convites e descarta as assinaturas coletadas (com confirmação da quantidade).
     */
    public function reopen(Request $request, FeedbackSession $session, ReopenFeedbackSession $reopen): RedirectResponse
    {
        $company = $this->company($request);
        $this->authorizeSession($company, $session);

        $data = $request->validate([
            'confirmed' => ['accepted'],
            'signed_count' => ['required', 'integer', 'min:0'],
        ], [
            'confirmed.accepted' => 'Confirme que os convites serão cancelados e as assinaturas coletadas, descartadas.',
        ]);

        $result = $reopen->execute($session, $request->user(), (int) $data['signed_count']);

        $message = match ($result['signed']) {
            0 => 'Feedback reaberto para edição. Os convites de assinatura foram cancelados.',
            1 => 'Feedback reaberto para edição. Os convites foram cancelados e 1 assinatura foi descartada.',
            default => "Feedback reaberto para edição. Os convites foram cancelados e {$result['signed']} assinaturas foram descartadas.",
        };

        return $this->feedbackRedirect('sessions.show', $session, $message);
    }

    public function pdf(Request $request, FeedbackSession $session, FeedbackPdfService $pdf): HttpResponse
    {
        $company = $this->company($request);
        $this->authorizeSession($company, $session);
        abort_unless(FeedbackVisibility::actsAsCompanyAdmin($request->user()), 403);

        $filename = 'feedback-'.$session->id.'.pdf';

        return $pdf->download($session)->download($filename);
    }

    /**
     * Exclui o feedback e todas as respostas. Só quem administra a empresa (no admin, com «excluir» em Feedbacks);
     * exige confirmação explícita, também em requisição direta.
     */
    public function destroy(Request $request, FeedbackSession $session, DeleteFeedbackSession $delete): RedirectResponse
    {
        $company = $this->company($request);
        $this->authorizeSession($company, $session);
        abort_unless($this->canDelete($request), 403, 'Sem permissão para excluir feedbacks.');

        $request->validate(
            ['confirmed' => ['accepted']],
            ['confirmed.accepted' => 'Confirme que o feedback e todas as respostas serão excluídos.'],
        );

        $delete->execute($session, $request->user());

        return $this->feedbackRedirect('sessions.index', [], 'Feedback excluído.');
    }

    /**
     * Excluir feedback: administrador da empresa no portal (com a permissão de excluir do módulo); no admin da
     * Talents, a matriz «feedbacks» com «excluir». Líder comum (company_user) não exclui.
     */
    private function canDelete(Request $request): bool
    {
        $user = $request->user();

        if (app(FeedbackCompanyContext::class)->isAdminContext($request)) {
            return $user->canAccessAdmin(AdminPermissionModule::Feedbacks, PermissionAction::Delete);
        }

        return $user->isCompanyAdmin() && $user->canAccess(PermissionModule::Feedbacks, PermissionAction::Delete);
    }

    /**
     * Opções de «Líder responsável»: os acessos ativos da empresa do feedback (User::withActiveCompanyAccess).
     * Rótulo «Nome (papel)»; com nome repetido, acrescenta o e-mail para distinguir.
     *
     * @return Collection<int, array{id: int, name: string, email: string, label: string}>
     */
    private function leaderOptions(Company $company): Collection
    {
        $users = User::query()
            ->withActiveCompanyAccess($company->id)
            ->with(['workspaces' => fn ($q) => $q
                ->where('workspace_type', WorkspaceType::Company)
                ->where('company_id', $company->id)])
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $nameCounts = $users->countBy(fn (User $u) => mb_strtolower(trim((string) $u->name)));

        return $users->map(function (User $u) use ($nameCounts): array {
            $role = $u->workspaces->first()?->role?->label();
            $details = array_filter([
                $role,
                $nameCounts[mb_strtolower(trim((string) $u->name))] > 1 ? $u->email : null,
            ]);

            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'label' => $details ? $u->name.' ('.implode(' · ', $details).')' : $u->name,
            ];
        })->values();
    }

    /**
     * @param  array<int|string, mixed>  $answers
     */
    private function syncAnswers(FeedbackSession $session, array $answers, User $user): void
    {
        // Só perguntas da versão do feedback (respostas fora da versão atual ficam como estão).
        $questionsQuery = FeedbackTemplateQuestion::query()
            ->whereIn('id', FeedbackSessionStructure::questionIds($session));

        if (! FeedbackVisibility::canViewLeaderSelfSections($user)) {
            $questionsQuery->whereHas(
                'section',
                fn ($q) => $q->where('audience', '!=', FeedbackVisibility::AUDIENCE_LEADER_SELF),
            );
        }

        $questionIds = $questionsQuery->pluck('id');

        foreach ($answers as $questionId => $payload) {
            if (! $questionIds->contains((int) $questionId)) {
                continue;
            }

            $valueText = null;
            $valueJson = null;

            if (is_array($payload)) {
                $valueJson = $payload;
            } elseif ($payload !== null && $payload !== '') {
                $valueText = (string) $payload;
            } else {
                FeedbackSessionAnswer::query()
                    ->where('feedback_session_id', $session->id)
                    ->where('feedback_template_question_id', $questionId)
                    ->forceDelete(); // resposta apagada na edição; a exclusão lógica é só para excluir o feedback

                continue;
            }

            FeedbackSessionAnswer::updateOrCreate(
                [
                    'feedback_session_id' => $session->id,
                    'feedback_template_question_id' => $questionId,
                ],
                [
                    'value_text' => $valueText,
                    'value_json' => $valueJson,
                ],
            );
        }
    }

    /**
     * @param  array<int|string, array{question?: string, answer?: string}>  $extras
     * @return array<string, array{question: string, answer: string}>|null
     */
    private function normalizeSectionExtras(FeedbackSession $session, array $extras, User $user): ?array
    {
        $versionId = FeedbackSessionStructure::version($session)->id;
        $sectionsQuery = FeedbackTemplateSection::query()
            ->where('feedback_template_version_id', $versionId)
            ->where('section_type', '!=', 'intro');

        if (! FeedbackVisibility::canViewLeaderSelfSections($user)) {
            $sectionsQuery->where('audience', '!=', FeedbackVisibility::AUDIENCE_LEADER_SELF);
        }

        $validSectionIds = $sectionsQuery
            ->pluck('id')
            ->map(fn ($id) => (string) $id);

        $normalized = [];

        foreach ($extras as $sectionId => $extra) {
            if (! $validSectionIds->contains((string) $sectionId) || ! is_array($extra)) {
                continue;
            }

            $question = trim((string) ($extra['question'] ?? ''));
            $answer = trim((string) ($extra['answer'] ?? ''));

            if ($question === '' && $answer === '') {
                continue;
            }

            $normalized[(string) $sectionId] = [
                'question' => $question,
                'answer' => $answer,
            ];
        }

        // Perguntas extras de seções fora da versão atual (feedback atualizado): preservadas como estão.
        $versionSectionIds = FeedbackTemplateSection::query()
            ->where('feedback_template_version_id', $versionId)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->flip();
        foreach ($session->section_extras ?? [] as $sectionId => $extra) {
            if (! $versionSectionIds->has((string) $sectionId) && is_array($extra)) {
                $normalized[(string) $sectionId] = $extra;
            }
        }

        if (! FeedbackVisibility::canViewLeaderSelfSections($user)) {
            $leaderSelfSectionIds = FeedbackTemplateSection::query()
                ->where('feedback_template_version_id', $versionId)
                ->where('audience', FeedbackVisibility::AUDIENCE_LEADER_SELF)
                ->pluck('id')
                ->map(fn ($id) => (string) $id);

            $existing = $session->section_extras ?? [];
            foreach ($leaderSelfSectionIds as $sectionId) {
                if (! isset($existing[$sectionId]) || ! is_array($existing[$sectionId])) {
                    continue;
                }

                $question = trim((string) ($existing[$sectionId]['question'] ?? ''));
                $answer = trim((string) ($existing[$sectionId]['answer'] ?? ''));

                if ($question === '' && $answer === '') {
                    continue;
                }

                $normalized[$sectionId] = [
                    'question' => $question,
                    'answer' => $answer,
                ];
            }
        }

        return $normalized === [] ? null : $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionPayload(FeedbackSession $session, User $user): array
    {
        $session->load([
            'employee.department',
            'employee.position',
            'leader',
            'templateVersion.sections.questions',
            'answers',
            'signatures',
        ]);

        $canViewLeaderSelf = FeedbackVisibility::canViewLeaderSelfSections($user);

        // Estrutura, título e descrição da versão usada pelo feedback (não da versão atual do modelo).
        $template = FeedbackSessionStructure::templatePayload($session, $canViewLeaderSelf);
        $visibleSections = collect($template['sections']);
        $visibleQuestionIds = $visibleSections->flatMap(fn ($s) => collect($s['questions'])->pluck('id'))->map(fn ($id) => (int) $id)->flip();
        $visibleSectionIds = $visibleSections->pluck('id')->map(fn ($id) => (string) $id)->flip();

        $answers = [];
        foreach ($session->answers as $answer) {
            if ($visibleQuestionIds->has((int) $answer->feedback_template_question_id)) {
                $answers[$answer->feedback_template_question_id] = $answer->value_json ?? $answer->value_text;
            }
        }

        $sectionExtras = collect($session->section_extras ?? [])
            ->filter(fn ($_, $sectionId) => $visibleSectionIds->has((string) $sectionId))
            ->all();

        $latest = $this->latestVersion($session);
        $canEditSession = $this->userCanEditSession($user);

        return [
            'id' => $session->id,
            'title' => $session->title,
            'status' => $session->status->value,
            'status_label' => $session->status->label(),
            'scheduled_at' => $session->scheduled_at?->toIso8601String(),
            'next_alignment_at' => $session->next_alignment_at?->toIso8601String(),
            'completed_at' => $session->completed_at?->toIso8601String(),
            'employee' => $session->collaboratorPayload(),
            // Cadastro do colaborador, para o link «Ver pessoa» (histórico e Evolução).
            'employee_profile_id' => $session->company_employee_id,
            'leader' => $session->leader?->only(['id', 'name', 'email']),
            'template' => $template,
            'answers' => $answers,
            'answers_count' => $session->answers->count(),
            'section_extras' => $sectionExtras,
            // Respostas e perguntas extras guardadas de uma versão anterior (feedback atualizado de versão).
            'out_of_version_answers' => FeedbackSessionStructure::outOfVersionAnswers($session, $canViewLeaderSelf),
            'out_of_version_extras' => FeedbackSessionStructure::outOfVersionExtras($session, $canViewLeaderSelf),
            'version' => [
                'id' => $template['version_id'],
                'number' => $template['version_number'],
                'latest_id' => $latest->id,
                'latest_number' => $latest->number,
                'can_update' => $canEditSession && $session->isOpenForEditing() && (int) $latest->id !== (int) $template['version_id'],
            ],
            'can_edit' => $canEditSession && $this->isEditableStatus($session),
            'reopen' => $canEditSession && $session->status === FeedbackSessionStatus::AwaitingSignatures
                ? app(ReopenFeedbackSession::class)->impact($session)
                : null,
            'signatures' => $session->signatures->map(fn ($signature) => [
                'id' => $signature->id,
                'role' => $signature->role->value,
                'role_label' => $signature->role->label(),
                'signer_name' => $signature->signer_name,
                'signer_email' => $signature->signer_email,
                'sent_at' => $signature->sent_at?->toIso8601String(),
                'signed_at' => $signature->signed_at?->toIso8601String(),
                'sign_url' => $signature->isSigned()
                    ? null
                    : route('feedback.sign.show', $signature->token),
            ]),
        ];
    }

    /**
     * @return Collection<int, FeedbackTemplate>
     */
    private function availableTemplates(Company $company)
    {
        return FeedbackTemplate::query()
            ->where(function ($q) use ($company) {
                $q->whereNull('company_id')->orWhere('company_id', $company->id);
            })
            ->whereNull('archived_at')
            ->orderByDesc('is_default')
            ->orderBy('title')
            ->get(['id', 'title', 'description', 'is_default']);
    }

    /** Concluído, cancelado ou com convite de assinatura: o conteúdo não muda (reabrir antes, se preciso). */
    private function isEditableStatus(FeedbackSession $session): bool
    {
        return ! in_array($session->status, [
            FeedbackSessionStatus::Completed,
            FeedbackSessionStatus::Cancelled,
            FeedbackSessionStatus::AwaitingSignatures,
        ], true);
    }

    private function abortUnlessEditable(FeedbackSession $session): void
    {
        abort_unless(
            $this->isEditableStatus($session),
            403,
            $session->status === FeedbackSessionStatus::AwaitingSignatures
                ? 'Este feedback aguarda assinaturas e não pode ser editado. Use «Reabrir para edição» para alterá-lo.'
                : 'Este feedback não pode mais ser editado.',
        );
    }

    /** «Editar» em Feedbacks: no admin, a matriz do módulo; no portal, a permissão do módulo (RH e líder). */
    private function userCanEditSession(User $user): bool
    {
        if (app(FeedbackCompanyContext::class)->isAdminContext(request())) {
            return $user->canAccessAdmin(AdminPermissionModule::Feedbacks, PermissionAction::Edit);
        }

        return $user->canAccess(PermissionModule::Feedbacks, PermissionAction::Edit);
    }

    private function latestVersion(FeedbackSession $session): FeedbackTemplateVersion
    {
        $template = $session->template()->withTrashed()->firstOrFail();

        return app(FeedbackTemplateVersioning::class)->currentVersion($template);
    }

    private function authorizeSession(Company $company, FeedbackSession $session): void
    {
        abort_unless($session->company_id === $company->id, 403);
        FeedbackVisibility::authorizeSession(request()->user(), $session);
    }
}
