<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\Feedback;

use App\Actions\Company\ResolveOrCreateCompanyEmployee;
use App\Actions\Feedback\SendFeedbackSignatureInvites;
use App\Enums\FeedbackSessionStatus;
use App\Http\Controllers\Concerns\ResolvesFeedbackRoutes;
use App\Models\FeedbackSession;
use App\Models\FeedbackSessionAnswer;
use App\Models\FeedbackTemplate;
use App\Models\FeedbackTemplateQuestion;
use App\Models\FeedbackTemplateSection;
use App\Models\User;
use App\Support\Company\CompanyEmployeeDirectory;
use App\Support\Feedback\FeedbackVisibility;
use App\Services\Feedback\FeedbackPdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ]);

        return Inertia::render('Client/Feedbacks/Sessions/Index', [
            'sessions' => $sessions,
        ]);
    }

    public function create(Request $request): Response
    {
        $company = $this->company($request);

        return Inertia::render('Client/Feedbacks/Sessions/Create', [
            'leaders' => User::query()
                ->where('company_id', $company->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
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
            'feedback_template_id' => ['required', 'exists:feedback_templates,id'],
            'leader_user_id' => ['required', 'exists:users,id'],
            'scheduled_at' => ['required', 'date'],
            'next_alignment_at' => ['nullable', 'date'],
            'title' => ['nullable', 'string', 'max:255'],
        ], [], [
            'employee_name' => 'nome do colaborador',
            'employee_email' => 'e-mail do colaborador',
            'scheduled_at' => 'data do alinhamento',
        ]);

        $employeeName = trim($data['employee_name']);
        $employeeEmail = isset($data['employee_email']) ? trim((string) $data['employee_email']) : '';
        $employeeEmail = $employeeEmail !== '' ? $employeeEmail : null;

        $template = FeedbackTemplate::query()->findOrFail($data['feedback_template_id']);
        abort_unless($this->templateAvailableToCompany($company, $template), 403);

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
        ]);
    }

    public function edit(Request $request, FeedbackSession $session): Response
    {
        $company = $this->company($request);
        $this->authorizeSession($company, $session);
        abort_if(in_array($session->status, [FeedbackSessionStatus::Completed, FeedbackSessionStatus::Cancelled], true), 403);

        return Inertia::render('Client/Feedbacks/Sessions/Edit', [
            'session' => $this->sessionPayload($session, $request->user()),
        ]);
    }

    public function update(Request $request, FeedbackSession $session): RedirectResponse
    {
        $company = $this->company($request);
        $this->authorizeSession($company, $session);
        abort_if(in_array($session->status, [FeedbackSessionStatus::Completed, FeedbackSessionStatus::Cancelled], true), 403);

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

    public function pdf(Request $request, FeedbackSession $session, FeedbackPdfService $pdf): HttpResponse
    {
        $company = $this->company($request);
        $this->authorizeSession($company, $session);
        abort_unless(FeedbackVisibility::actsAsCompanyAdmin($request->user()), 403);

        $filename = 'feedback-'.$session->id.'.pdf';

        return $pdf->download($session)->download($filename);
    }

    /**
     * @param  array<int|string, mixed>  $answers
     */
    private function syncAnswers(FeedbackSession $session, array $answers, User $user): void
    {
        $questionsQuery = FeedbackTemplateQuestion::query()
            ->whereHas('section', fn ($q) => $q->where('feedback_template_id', $session->feedback_template_id));

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
                    ->delete();

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
        $sectionsQuery = FeedbackTemplateSection::query()
            ->where('feedback_template_id', $session->feedback_template_id)
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

        if (! FeedbackVisibility::canViewLeaderSelfSections($user)) {
            $leaderSelfSectionIds = FeedbackTemplateSection::query()
                ->where('feedback_template_id', $session->feedback_template_id)
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
            'template.sections.questions',
            'answers',
            'signatures',
        ]);

        $canViewLeaderSelf = FeedbackVisibility::canViewLeaderSelfSections($user);

        if ($session->template && ! $canViewLeaderSelf) {
            $session->template->setRelation(
                'sections',
                $session->template->sections
                    ->reject(fn (FeedbackTemplateSection $section) => $section->audience === FeedbackVisibility::AUDIENCE_LEADER_SELF)
                    ->values(),
            );
        }

        $hiddenQuestionIds = collect();
        if (! $canViewLeaderSelf && $session->template) {
            $hiddenQuestionIds = FeedbackTemplateQuestion::query()
                ->whereHas(
                    'section',
                    fn ($q) => $q
                        ->where('feedback_template_id', $session->feedback_template_id)
                        ->where('audience', FeedbackVisibility::AUDIENCE_LEADER_SELF),
                )
                ->pluck('id');
        }

        $answers = [];
        foreach ($session->answers as $answer) {
            if ($hiddenQuestionIds->contains($answer->feedback_template_question_id)) {
                continue;
            }
            $answers[$answer->feedback_template_question_id] = $answer->value_json ?? $answer->value_text;
        }

        $sectionExtras = $session->section_extras ?? [];
        if (! $canViewLeaderSelf && $session->template) {
            $hiddenSectionIds = FeedbackTemplateSection::query()
                ->where('feedback_template_id', $session->feedback_template_id)
                ->where('audience', FeedbackVisibility::AUDIENCE_LEADER_SELF)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all();

            $sectionExtras = collect($sectionExtras)
                ->reject(fn ($_, $sectionId) => in_array((string) $sectionId, $hiddenSectionIds, true))
                ->all();
        }

        return [
            'id' => $session->id,
            'title' => $session->title,
            'status' => $session->status->value,
            'status_label' => $session->status->label(),
            'scheduled_at' => $session->scheduled_at?->toIso8601String(),
            'next_alignment_at' => $session->next_alignment_at?->toIso8601String(),
            'completed_at' => $session->completed_at?->toIso8601String(),
            'employee' => $session->collaboratorPayload(),
            'leader' => $session->leader?->only(['id', 'name', 'email']),
            'template' => $session->template,
            'answers' => $answers,
            'section_extras' => $sectionExtras,
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
     * @return \Illuminate\Support\Collection<int, FeedbackTemplate>
     */
    private function availableTemplates(\App\Models\Company $company)
    {
        return FeedbackTemplate::query()
            ->where(function ($q) use ($company) {
                $q->whereNull('company_id')->orWhere('company_id', $company->id);
            })
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('title')
            ->get(['id', 'title', 'description', 'is_default']);
    }

    private function templateAvailableToCompany(\App\Models\Company $company, FeedbackTemplate $template): bool
    {
        return $template->company_id === null || $template->company_id === $company->id;
    }

    private function authorizeSession(\App\Models\Company $company, FeedbackSession $session): void
    {
        abort_unless($session->company_id === $company->id, 403);
        FeedbackVisibility::authorizeSession(request()->user(), $session);
    }
}
