<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\Feedback;

use App\Actions\Feedback\MergeCompanyEmployees;
use App\Actions\Feedback\StartFeedbackEvolution;
use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Http\Controllers\Concerns\ResolvesFeedbackRoutes;
use App\Models\CompanyEmployee;
use App\Services\Feedback\Ai\FeedbackAiException;
use App\Services\Feedback\FeedbackEvolutionBuilder;
use App\Support\Feedback\Ai\FeedbackAiGate;
use App\Support\Feedback\Ai\FeedbackAiMonthlyLimit;
use App\Support\Feedback\Ai\FeedbackEvolutionPresenter;
use App\Support\Feedback\FeedbackCompanyContext;
use App\Support\Feedback\FeedbackEmployeeDuplicateFinder;
use App\Support\Feedback\FeedbackEmployeeMergeAccess;
use App\Support\Feedback\FeedbackVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pessoa em Feedbacks: página do colaborador com o histórico e a Evolução (só das sessões que o usuário vê),
 * sugestão de cadastros duplicados e união (só para quem administra os cadastros). Cadastro, edição e exclusão
 * continuam desativados: o colaborador é informado por nome nos fluxos e registrado automaticamente.
 */
class FeedbackEmployeeController extends FeedbackCompanyController
{
    use ResolvesFeedbackRoutes;

    public function index(Request $request): RedirectResponse
    {
        return $this->deprecatedRedirect($request);
    }

    public function create(Request $request): RedirectResponse
    {
        return $this->deprecatedRedirect($request);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->deprecatedRedirect($request);
    }

    public function edit(Request $request, CompanyEmployee $employee): RedirectResponse
    {
        return $this->deprecatedRedirect($request);
    }

    public function update(Request $request, CompanyEmployee $employee): RedirectResponse
    {
        return $this->deprecatedRedirect($request);
    }

    public function destroy(Request $request, CompanyEmployee $employee): RedirectResponse
    {
        return $this->deprecatedRedirect($request);
    }

    /** Página da pessoa: histórico e Evolução, limitados ao que o usuário vê. */
    public function show(Request $request, CompanyEmployee $employee, FeedbackEvolutionBuilder $builder): Response|RedirectResponse
    {
        $company = $this->company($request);
        abort_unless($employee->company_id === $company->id, 403);

        if ($employee->merged_into_employee_id !== null) {
            return $this->feedbackRedirect('employees.show', $employee->resolveMerged(), 'Este cadastro foi unido a outro; você está vendo o cadastro que ficou.');
        }

        $user = $request->user();
        $sessions = $builder->visibleSessions($employee, $user);
        abort_if(! FeedbackVisibility::actsAsCompanyAdmin($user) && $sessions->isEmpty(), 403);

        $evolution = $builder->build($employee, $user);
        $narrative = $builder->latestVisibleNarrative($employee, $sessions->pluck('id')->all());
        $reports = $builder->currentReports($sessions);
        $gate = app(FeedbackAiGate::class)->status();

        return Inertia::render('Client/Feedbacks/Employees/Show', [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
                'is_active' => $employee->is_active,
            ],
            'sessions' => $sessions->map(fn ($s) => [
                'id' => $s->id,
                'title' => $s->title,
                'status' => $s->status->value,
                'status_label' => $s->status->label(),
                'scheduled_at' => $s->scheduled_at?->toIso8601String(),
                'period_label' => FeedbackEvolutionBuilder::periodLabel($s->scheduled_at),
                'leader' => $s->leader?->name,
                'template' => $s->template?->title,
                'has_report' => $reports->has($s->id),
            ])->values(),
            'evolution' => collect($evolution)->except(['eligible_report_ids'])->all(),
            'narrative' => FeedbackEvolutionPresenter::present($narrative, $evolution['eligible_session_ids']),
            'ai' => [
                'allowed' => $gate['allowed'],
                'provider_label' => $gate['provider_label'],
                'model' => $gate['model'],
                'limit_reached' => FeedbackAiMonthlyLimit::usedCents($company->id) >= FeedbackAiMonthlyLimit::limitCents($company),
                'can_generate' => $this->canUseAi($request),
            ],
            'canManageEmployees' => FeedbackEmployeeMergeAccess::canManage($request),
        ]);
    }

    /** Pede a narrativa de Evolução por IA (relatórios das sessões visíveis ao usuário). */
    public function evolution(Request $request, CompanyEmployee $employee, StartFeedbackEvolution $start): RedirectResponse
    {
        $company = $this->company($request);
        abort_unless($employee->company_id === $company->id && $employee->merged_into_employee_id === null, 403);
        abort_unless($this->canUseAi($request), 403);
        abort_if(! FeedbackVisibility::actsAsCompanyAdmin($request->user())
            && FeedbackVisibility::scopeSessions($employee->feedbackSessions()->getQuery(), $request->user())->doesntExist(), 403);

        try {
            $start->execute($employee, $request->user());
        } catch (FeedbackAiException $e) {
            return back()->withErrors(['ai' => $e->getMessage()]);
        }

        return back()->with('success', 'Evolução em geração. O resultado aparece aqui quando ficar pronto.');
    }

    /** Pares de cadastros que podem ser a mesma pessoa (só sugestão). */
    public function duplicates(Request $request, FeedbackEmployeeDuplicateFinder $finder): Response
    {
        $company = $this->company($request);
        abort_unless(FeedbackEmployeeMergeAccess::canManage($request), 403);

        $card = fn (CompanyEmployee $e) => [
            'id' => $e->id,
            'name' => $e->name,
            'email' => $e->email,
            'sessions_count' => (int) ($e->feedback_sessions_count ?? 0),
            'created_at' => $e->created_at?->toIso8601String(),
        ];

        return Inertia::render('Client/Feedbacks/Employees/Duplicates', [
            'pairs' => array_map(fn ($p) => [
                'a' => $card($p['a']),
                'b' => $card($p['b']),
                'reason' => $p['reason'],
                'reason_label' => $p['reason_label'],
            ], $finder->forCompany($company)),
        ]);
    }

    /** Pré-visualização da união (nada é alterado). */
    public function mergePreview(Request $request, MergeCompanyEmployees $merge): Response
    {
        [$primary, $absorbed] = $this->pair($request);

        return Inertia::render('Client/Feedbacks/Employees/Merge', [
            'primary' => $this->employeeCard($primary),
            'absorbed' => $this->employeeCard($absorbed),
            'preview' => $merge->preview($primary, $absorbed),
            'tables' => MergeCompanyEmployees::LINKED_TABLES,
        ]);
    }

    public function merge(Request $request, MergeCompanyEmployees $merge): RedirectResponse
    {
        [$primary, $absorbed] = $this->pair($request);
        $data = $request->validate(
            ['confirmed' => ['accepted'], 'counts' => ['required', 'array']],
            ['confirmed.accepted' => 'Confirme que entendeu que a união não pode ser desfeita pela interface.', 'counts.required' => MergeCompanyEmployees::STALE_MESSAGE],
        );

        $result = $merge->execute($primary, $absorbed, $request->user(), array_map('intval', $data['counts']));

        return $this->feedbackRedirect(
            'employees.show',
            $primary,
            $result['already_merged'] ? 'Esses cadastros já estavam unidos.' : 'Cadastros unidos. O histórico agora está numa linha do tempo só.',
        );
    }

    /** @return array{0: CompanyEmployee, 1: CompanyEmployee} */
    private function pair(Request $request): array
    {
        $company = $this->company($request);
        abort_unless(FeedbackEmployeeMergeAccess::canManage($request), 403);

        $data = $request->validate(
            ['principal' => ['required', 'integer'], 'absorvido' => ['required', 'integer', 'different:principal']],
            ['absorvido.different' => 'Escolha dois cadastros diferentes.'],
        );

        $find = fn (int $id) => CompanyEmployee::query()->whereKey($id)->first() ?? abort(404);
        $primary = $find((int) $data['principal']);
        $absorbed = $find((int) $data['absorvido']);

        // Nunca une cadastros de empresas diferentes, nem fora da empresa do contexto.
        abort_unless($primary->company_id === $company->id && $absorbed->company_id === $company->id, 403);

        return [$primary, $absorbed];
    }

    /** @return array<string, mixed> */
    private function employeeCard(CompanyEmployee $e): array
    {
        $e->loadCount('feedbackSessions');

        return [
            'id' => $e->id,
            'name' => $e->name,
            'email' => $e->email,
            'is_active' => $e->is_active,
            'sessions_count' => (int) $e->feedback_sessions_count,
            'created_at' => $e->created_at?->toIso8601String(),
        ];
    }

    /** Gerar a narrativa: mesma regra do processamento da gravação («criar» em Feedbacks). */
    private function canUseAi(Request $request): bool
    {
        $user = $request->user();

        return app(FeedbackCompanyContext::class)->isAdminContext($request)
            ? $user->canAccessAdmin(AdminPermissionModule::Feedbacks, PermissionAction::Create)
            : $user->canAccess(PermissionModule::Feedbacks, PermissionAction::Create);
    }

    private function deprecatedRedirect(Request $request): RedirectResponse
    {
        $this->company($request);

        return $this->feedbackRedirect(
            'index',
            message: 'O colaborador é informado por nome nos fluxos de Feedback, Férias e Desligamento e fica registrado na empresa automaticamente.',
        );
    }
}
