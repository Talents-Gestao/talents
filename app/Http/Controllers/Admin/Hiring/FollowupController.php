<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hiring;

use App\Enums\AdminPermissionModule;
use App\Enums\FollowupStatus;
use App\Enums\PermissionAction;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\EmploymentCycle;
use App\Models\EmploymentCycleEvent;
use App\Models\HiringFollowupInvitation;
use App\Models\HiringFollowupMilestone;
use App\Models\User;
use App\Services\Hiring\EmploymentCycleService;
use App\Services\Hiring\FollowupInvitationService;
use App\Services\Hiring\HiringCommunicationService;
use App\Support\Hiring\FollowupPayload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contratação → Pós-contratação (contratacao_pos): fila, detalhe do ciclo, admissão, pausa, desligamento,
 * readmissão, marcos e convites. Ver: view; admissão, convites e anotações: edit; desligar e readmitir: delete.
 * Rotas com scopeBindings: marco de outro ciclo e convite de outro marco dão 404.
 */
class FollowupController extends Controller
{
    public function __construct(
        private readonly EmploymentCycleService $cycles,
        private readonly FollowupInvitationService $invitations,
    ) {}

    public function index(Request $request): Response
    {
        $status = FollowupStatus::tryFrom((string) $request->query('status', ''));
        $companyId = $request->integer('company_id') ?: null;
        $search = trim($request->string('q')->toString());

        $query = EmploymentCycle::query()
            ->with(['employee:id,name', 'company:id,name', 'manager', 'milestones'])
            ->latest('id');

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }
        if ($search !== '') {
            $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $like = '%'.$search.'%';
            $query->where(function (Builder $q) use ($operator, $like): void {
                $q->whereHas('employee', fn (Builder $e) => $e->where('name', $operator, $like))
                    ->orWhereHas('company', fn (Builder $c) => $c->where('name', $operator, $like))
                    ->orWhere('role_title', $operator, $like);
            });
        }

        // O status é calculado (classe pura), então o filtro é aplicado depois da consulta.
        $rows = $query->get()
            ->map(fn (EmploymentCycle $c) => FollowupPayload::queueRow($c))
            ->when($status !== null, fn ($rows) => $rows->where('status', $status->value))
            ->values();

        $page = max(1, $request->integer('page', 1));
        $perPage = 25;
        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('Admin/Hiring/Followup/Index', [
            'cycles' => $paginator,
            'statuses' => FollowupStatus::options(),
            'companies' => Company::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'status' => $status?->value,
                'company_id' => $companyId,
                'q' => $search !== '' ? $search : null,
            ],
            'can' => $this->abilities($request->user()),
            'emails_remaining_today' => app(HiringCommunicationService::class)->emailsRemainingToday(),
        ]);
    }

    public function show(Request $request, EmploymentCycle $cycle): Response
    {
        $cycle->load([
            'employee:id,name,email,company_id',
            'company:id,name',
            'manager',
            'milestones.invitations.response',
            'application.vacancy:id,title',
            'events' => fn ($q) => $q->with('user:id,name')->limit(200),
        ]);
        $user = $request->user();
        $status = $cycle->followupStatus();

        $previous = EmploymentCycle::query()
            ->where('company_id', $cycle->company_id)
            ->where('company_employee_id', $cycle->company_employee_id)
            ->whereKeyNot($cycle->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (EmploymentCycle $c) => [
                'id' => $c->id,
                'status_label' => $c->status->label(),
                'admission_date' => $c->admission_date?->toDateString(),
                'terminated_on' => $c->terminated_on?->toDateString(),
                'role_title' => $c->role_title,
                'url' => route('admin.contratacao.pos.show', $c),
            ]);

        $candidateId = $cycle->application?->talent_candidate_id;

        return Inertia::render('Admin/Hiring/Followup/Show', [
            'cycle' => [
                'id' => $cycle->id,
                'status' => $cycle->status->value,
                'status_label' => $cycle->status->label(),
                'followup_status' => $status->value,
                'followup_status_label' => $status->label(),
                'employee' => ['id' => $cycle->employee?->id, 'name' => $cycle->employee?->name],
                'company' => ['id' => $cycle->company?->id, 'name' => $cycle->company?->name],
                'role_title' => $cycle->role_title,
                'manager' => $cycle->manager ? [
                    'id' => $cycle->manager->id,
                    'name' => $cycle->manager->name,
                    'role' => $cycle->manager->role,
                    'email' => $cycle->manager->email,
                    'phone' => $cycle->manager->phone,
                ] : null,
                'collaborator_email' => $cycle->collaborator_email,
                'collaborator_phone' => $cycle->collaborator_phone,
                'collaborator_email_opt_in' => $cycle->collaborator_email_opt_in,
                'admission_date' => $cycle->admission_date?->toDateString(),
                'active_confirmed_at' => $cycle->active_confirmed_at?->toIso8601String(),
                'paused_at' => $cycle->paused_at?->toIso8601String(),
                'pause_reason' => $cycle->pause_reason,
                'terminated_on' => $cycle->terminated_on?->toDateString(),
                'termination_note' => $cycle->termination_note,
                'vacancy' => $cycle->application?->vacancy ? [
                    'title' => $cycle->application->vacancy->title,
                    'url' => $user->canAccessAdmin(AdminPermissionModule::ContratacaoVagas, PermissionAction::View)
                        ? route('admin.contratacao.vagas.show', $cycle->application->vacancy)
                        : null,
                ] : null,
                'candidate_url' => $candidateId && $user->canAccessAdmin(AdminPermissionModule::ContratacaoCandidatos, PermissionAction::View)
                    ? route('admin.contratacao.candidatos.show', $candidateId)
                    : null,
            ],
            'milestones' => $cycle->milestones->map(fn (HiringFollowupMilestone $m) => FollowupPayload::milestone($cycle, $m))->values(),
            'events' => $cycle->events->map(fn (EmploymentCycleEvent $e) => [
                'id' => $e->id,
                'type' => $e->type->value,
                'label' => $e->type->label(),
                'note' => $e->note,
                'fields' => $e->meta['fields'] ?? [],
                'user_name' => $e->user?->name,
                'created_at' => $e->created_at?->toIso8601String(),
            ])->values(),
            'previous_cycles' => $previous,
            'manager_options' => FollowupPayload::managerOptions((int) $cycle->company_id),
            'can' => $this->abilities($user),
            'emails_remaining_today' => app(HiringCommunicationService::class)->emailsRemainingToday(),
        ]);
    }

    public function update(Request $request, EmploymentCycle $cycle): RedirectResponse
    {
        $data = $request->validate([
            'role_title' => ['nullable', 'string', 'max:255'],
            'manager_contact_id' => ['nullable', 'integer'],
            'collaborator_email' => ['nullable', 'email', 'max:255'],
            'collaborator_phone' => ['nullable', 'string', 'max:40'],
            'collaborator_email_opt_in' => ['boolean'],
        ], [], ['role_title' => 'cargo', 'manager_contact_id' => 'gestor', 'collaborator_email' => 'e-mail do colaborador', 'collaborator_phone' => 'telefone do colaborador']);
        $data['collaborator_email_opt_in'] = (bool) ($data['collaborator_email_opt_in'] ?? false);

        $this->cycles->update($cycle, $data, $request->user()?->id);

        return back()->with('success', 'Vínculo atualizado.');
    }

    public function confirmAdmission(Request $request, EmploymentCycle $cycle): RedirectResponse
    {
        $data = $request->validate(['admission_date' => ['required', 'date_format:Y-m-d']], [], ['admission_date' => 'data de admissão']);
        $this->cycles->confirmAdmission($cycle, $data['admission_date'], $request->user()?->id);

        return back()->with('success', 'Admissão confirmada. Os marcos D15, D30, D60 e D90 foram criados; nada é enviado sozinho.');
    }

    public function pause(Request $request, EmploymentCycle $cycle): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']], [], ['reason' => 'motivo']);
        $this->cycles->pause($cycle, $data['reason'], $request->user()?->id);

        return back()->with('success', 'Acompanhamento pausado.');
    }

    public function resume(Request $request, EmploymentCycle $cycle): RedirectResponse
    {
        $this->cycles->resume($cycle, $request->user()?->id);

        return back()->with('success', 'Acompanhamento retomado.');
    }

    public function terminate(Request $request, EmploymentCycle $cycle): RedirectResponse
    {
        $data = $request->validate([
            'terminated_on' => ['nullable', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [], ['terminated_on' => 'data de desligamento', 'note' => 'observação']);
        $this->cycles->terminate($cycle, $data['terminated_on'] ?? null, $data['note'] ?? null, $request->user()?->id);

        return back()->with('success', 'Desligamento registrado. Os convites em aberto foram revogados.');
    }

    public function readmit(Request $request, EmploymentCycle $cycle): RedirectResponse
    {
        $data = $request->validate(['admission_date' => ['required', 'date_format:Y-m-d']], [
            'admission_date.required' => 'Informe a data da readmissão.',
        ], ['admission_date' => 'data da readmissão']);
        $new = $this->cycles->readmit($cycle, $data['admission_date'], $request->user()?->id);

        return redirect()->route('admin.contratacao.pos.show', $new)->with('success', 'Readmissão registrada num ciclo novo. O ciclo anterior foi preservado.');
    }

    public function storeNote(Request $request, EmploymentCycle $cycle): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']], [], ['note' => 'anotação']);
        $this->cycles->addNote($cycle, $data['note'], $request->user()?->id);

        return back()->with('success', 'Anotação registrada.');
    }

    /** «Enviar acompanhamento»: destino colaborador, gestor ou ambos; canal e-mail ou copiar link e mensagem. */
    public function send(Request $request, EmploymentCycle $cycle, HiringFollowupMilestone $milestone): JsonResponse
    {
        $data = $request->validate([
            'target' => ['required', Rule::in(['collaborator', 'manager', 'both'])],
            'channel' => ['required', Rule::in([FollowupInvitationService::CHANNEL_EMAIL, FollowupInvitationService::CHANNEL_COPY])],
        ], [], ['target' => 'destino', 'channel' => 'canal']);

        $results = $this->invitations->send($milestone, $data['target'], $data['channel'], $request->user()?->id);

        return response()->json([
            'results' => $results,
            'emails_remaining_today' => app(HiringCommunicationService::class)->emailsRemainingToday(),
        ]);
    }

    public function skip(Request $request, EmploymentCycle $cycle, HiringFollowupMilestone $milestone): RedirectResponse
    {
        $data = $request->validate(['skip_reason' => ['required', 'string', 'max:2000']], [], ['skip_reason' => 'motivo']);
        $this->cycles->skipMilestone($milestone, $data['skip_reason'], $request->user()?->id);

        return back()->with('success', 'Marco pulado.');
    }

    /** «Copiar»: registra copied_at automaticamente (nunca emailed_at). */
    public function copied(EmploymentCycle $cycle, HiringFollowupMilestone $milestone, HiringFollowupInvitation $invitation): JsonResponse
    {
        $invitation = $this->invitations->markCopied($invitation);

        return response()->json([
            'state' => $invitation->state()->value,
            'state_label' => $invitation->state()->label(),
            'copied_at' => $invitation->copied_at?->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, bool>
     */
    private function abilities(?User $user): array
    {
        $can = static fn (PermissionAction $a): bool => (bool) $user?->canAccessAdmin(AdminPermissionModule::ContratacaoPos, $a);

        return [
            'edit' => $can(PermissionAction::Edit),
            'delete' => $can(PermissionAction::Delete),
        ];
    }
}
