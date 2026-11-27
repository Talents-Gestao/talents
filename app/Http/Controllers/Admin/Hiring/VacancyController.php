<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hiring;

use App\Enums\AdminPermissionModule;
use App\Enums\HiringProcessStage;
use App\Enums\HiringVacancyStatus;
use App\Enums\PermissionAction;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\EmploymentCycle;
use App\Models\HiringApplication;
use App\Models\HiringApplicationEvent;
use App\Models\HiringProcess;
use App\Models\HiringVacancy;
use App\Models\HiringVacancyStage;
use App\Models\TalentCandidate;
use App\Services\Hiring\HiringVacancyService;
use App\Support\Hiring\HiringCommunicationsPayload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Vagas de contratação (uso interno da Talents). */
class VacancyController extends Controller
{
    public function __construct(
        private readonly HiringVacancyService $service,
    ) {}

    public function index(Request $request): Response
    {
        $companyId = $request->integer('company_id') ?: null;
        $processId = $request->integer('hiring_process_id') ?: null;
        $status = HiringVacancyStatus::tryFrom($request->string('status')->toString());
        $search = trim($request->string('q')->toString());

        $query = HiringVacancy::query()
            ->with(['company:id,name', 'hiringProcess:id,title,current_stage'])
            ->withCount([
                'applications',
                'applications as hired_count' => fn (Builder $q) => $q->whereNotNull('hired_at'),
            ])
            ->orderByRaw("case when status = 'open' then 0 when status = 'paused' then 1 else 2 end")
            ->orderByDesc('updated_at');

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }
        if ($processId !== null) {
            $query->where('hiring_process_id', $processId);
        }
        if ($status !== null) {
            $query->where('status', $status->value);
        }
        if ($search !== '') {
            $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $like = '%'.$search.'%';
            $query->where(function (Builder $q) use ($operator, $like): void {
                $q->where('title', $operator, $like)
                    ->orWhereHas('company', fn (Builder $c) => $c->where('name', $operator, $like))
                    ->orWhereHas('hiringProcess', fn (Builder $p) => $p->where('title', $operator, $like));
            });
        }

        $vacancies = $query->paginate(20)->withQueryString()->through(fn (HiringVacancy $v) => [
            'id' => $v->id,
            'title' => $v->title,
            'status' => $v->status->value,
            'status_label' => $v->status->label(),
            'company' => $v->company ? ['id' => $v->company->id, 'name' => $v->company->name] : null,
            'process' => $v->hiringProcess ? [
                'id' => $v->hiringProcess->id,
                'title' => $v->hiringProcess->title,
                'stage_label' => $v->hiringProcess->current_stage->label(),
            ] : null,
            'applications_count' => (int) $v->applications_count,
            'hired_count' => (int) $v->hired_count,
            'updated_at' => $v->updated_at?->toIso8601String(),
        ]);

        $processes = HiringProcess::query()
            ->with('company:id,name')
            ->orderBy('title')
            ->get(['id', 'company_id', 'title', 'current_stage'])
            ->map(fn (HiringProcess $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'company_id' => $p->company_id,
                'company_name' => $p->company?->name,
                'stage_label' => $p->current_stage->label(),
                'can_receive_vacancy' => $p->current_stage !== HiringProcessStage::Contratacao,
            ]);

        $companies = Company::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Company $c) => ['id' => $c->id, 'name' => $c->name]);

        $user = $request->user();

        return Inertia::render('Admin/Hiring/Vacancies/Index', [
            'vacancies' => $vacancies,
            'processes' => $processes,
            'companies' => $companies,
            'statuses' => HiringVacancyStatus::options(),
            'filters' => [
                'company_id' => $companyId,
                'hiring_process_id' => $processId,
                'status' => $status?->value,
                'q' => $search !== '' ? $search : null,
            ],
            'can' => [
                'create' => $user->canAccessAdmin(AdminPermissionModule::ContratacaoVagas, PermissionAction::Create),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'hiring_process_id' => ['required', 'integer', 'exists:hiring_processes,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ], [], [
            'hiring_process_id' => 'processo',
            'title' => 'título',
            'description' => 'descrição',
        ]);

        $process = HiringProcess::query()->findOrFail((int) $data['hiring_process_id']);
        $vacancy = $this->service->create($process, $data, $request->user()?->id);

        return redirect()
            ->route('admin.contratacao.vagas.show', $vacancy)
            ->with('success', 'Vaga criada com as etapas padrão.');
    }

    public function show(Request $request, HiringVacancy $vacancy): Response
    {
        $vacancy->load([
            'company:id,name',
            'hiringProcess:id,title,current_stage',
            'stages' => fn ($q) => $q->withCount('applications'),
        ]);

        $stageNames = $vacancy->stages->pluck('name', 'id');
        $canViewCandidates = (bool) $request->user()?->canAccessAdmin(
            AdminPermissionModule::ContratacaoCandidatos,
            PermissionAction::View,
        );
        $canViewFollowup = (bool) $request->user()?->canAccessAdmin(AdminPermissionModule::ContratacaoPos, PermissionAction::View);
        $canEditFollowup = (bool) $request->user()?->canAccessAdmin(AdminPermissionModule::ContratacaoPos, PermissionAction::Edit);
        $hiredStageIds = $vacancy->stages->where('is_hired', true)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $cyclesByApplication = EmploymentCycle::query()
            ->whereIn('hiring_application_id', HiringApplication::query()->select('id')->where('hiring_vacancy_id', $vacancy->id))
            ->get(['id', 'hiring_application_id', 'status'])
            ->keyBy('hiring_application_id');
        $canViewInterviews = (bool) $request->user()?->canAccessAdmin(
            AdminPermissionModule::EntrevistasIa,
            PermissionAction::View,
        );

        $applications = HiringApplication::query()
            ->where('hiring_vacancy_id', $vacancy->id)
            ->with([
                'candidate:id,name,email,phone,current_role,current_company,archived_at',
                'events' => fn ($q) => $q->with('user:id,name'),
                'assessment:id,hiring_application_id,decision,approved_at',
            ])
            ->withCount('interviews')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (HiringApplication $a) => [
                'id' => $a->id,
                'stage_id' => $a->hiring_vacancy_stage_id,
                'sort_order' => $a->sort_order,
                'hired_at' => $a->hired_at?->toIso8601String(),
                'candidate' => [
                    'id' => $a->candidate->id,
                    'name' => $a->candidate->name,
                    'email' => $a->candidate->email,
                    'phone' => $a->candidate->phone,
                    'current_role' => $a->candidate->current_role,
                    'current_company' => $a->candidate->current_company,
                    'archived' => $a->candidate->isArchived(),
                    'url' => $canViewCandidates ? route('admin.contratacao.candidatos.show', $a->candidate) : null,
                ],
                'interviews_count' => (int) $a->interviews_count,
                'interviews_url' => $canViewInterviews
                    ? route('admin.entrevistas.index', ['hiring_application_id' => $a->id])
                    : null,
                'assessment_url' => route('admin.contratacao.vagas.applications.assessment.show', [$vacancy, $a]),
                'assessment_label' => $a->assessment
                    ? ($a->assessment->isApproved() ? 'Dossiê aprovado' : $a->assessment->decision->label())
                    : null,
                // Pós-contratação: vínculo registrado (link para o ciclo) ou «Registrar admissão» na etapa de contratado.
                'cycle' => ($cycle = $cyclesByApplication->get($a->id)) ? [
                    'status_label' => $cycle->status->label(),
                    'url' => $canViewFollowup ? route('admin.contratacao.pos.show', $cycle->id) : null,
                ] : null,
                'admission' => ! $cyclesByApplication->has($a->id) && $canEditFollowup && in_array((int) $a->hiring_vacancy_stage_id, $hiredStageIds, true) ? [
                    'options_url' => route('admin.contratacao.vagas.applications.admission.options', [$vacancy, $a]),
                    'store_url' => route('admin.contratacao.vagas.applications.admission.store', [$vacancy, $a]),
                ] : null,
                'events' => $a->events->map(fn (HiringApplicationEvent $e) => [
                    'id' => $e->id,
                    'type' => $e->type->value,
                    'type_label' => $e->type->label(),
                    'from_stage_name' => $e->from_stage_id !== null ? ($stageNames[$e->from_stage_id] ?? null) : null,
                    'to_stage_name' => $e->to_stage_id !== null ? ($stageNames[$e->to_stage_id] ?? null) : null,
                    'note' => $e->note,
                    'user_name' => $e->user?->name,
                    'created_at' => $e->created_at?->toIso8601String(),
                ])->values()->all(),
            ]);

        $user = $request->user();

        return Inertia::render('Admin/Hiring/Vacancies/Show', [
            'vacancy' => [
                'id' => $vacancy->id,
                'title' => $vacancy->title,
                'description' => $vacancy->description,
                'status' => $vacancy->status->value,
                'status_label' => $vacancy->status->label(),
                'closed_at' => $vacancy->closed_at?->toIso8601String(),
                'company' => $vacancy->company ? ['id' => $vacancy->company->id, 'name' => $vacancy->company->name] : null,
                'process' => $vacancy->hiringProcess ? [
                    'id' => $vacancy->hiringProcess->id,
                    'title' => $vacancy->hiringProcess->title,
                    'stage_label' => $vacancy->hiringProcess->current_stage->label(),
                    'stage' => $vacancy->hiringProcess->current_stage->value,
                ] : null,
            ],
            'stages' => $vacancy->stages->map(fn (HiringVacancyStage $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'position' => $s->position,
                'is_hired' => $s->is_hired,
                'applications_count' => (int) $s->applications_count,
            ])->values(),
            'applications' => $applications,
            'statuses' => HiringVacancyStatus::options(),
            'can' => [
                'edit' => $user->canAccessAdmin(AdminPermissionModule::ContratacaoVagas, PermissionAction::Edit),
                'create' => $user->canAccessAdmin(AdminPermissionModule::ContratacaoVagas, PermissionAction::Create),
                'delete' => $user->canAccessAdmin(AdminPermissionModule::ContratacaoVagas, PermissionAction::Delete),
                'create_candidate' => $user->canAccessAdmin(AdminPermissionModule::ContratacaoCandidatos, PermissionAction::Create),
            ],
            // Comunicação de etapa aos contatos da empresa: histórico para quem vê; contatos para quem edita.
            'communications' => HiringCommunicationsPayload::history($vacancy),
            'communication' => $user->canAccessAdmin(AdminPermissionModule::ContratacaoVagas, PermissionAction::Edit)
                ? HiringCommunicationsPayload::audience($vacancy, $user)
                : null,
        ]);
    }

    public function update(Request $request, HiringVacancy $vacancy): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'required', Rule::enum(HiringVacancyStatus::class)],
        ], [], [
            'title' => 'título',
            'description' => 'descrição',
            'status' => 'situação',
        ]);

        $this->service->update($vacancy, $data, $request->user()?->id);

        return back()->with('success', 'Vaga atualizada.');
    }

    /** Até 10 candidatos ativos que ainda não estão na vaga (busca por nome, e-mail ou telefone). */
    public function availableCandidates(Request $request, HiringVacancy $vacancy): JsonResponse
    {
        $search = trim($request->string('q')->toString());

        $query = TalentCandidate::query()
            ->active()
            ->whereNotIn('id', HiringApplication::query()
                ->select('talent_candidate_id')
                ->where('hiring_vacancy_id', $vacancy->id))
            ->orderBy('name')
            ->limit(10);

        if ($search !== '') {
            $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $like = '%'.$search.'%';
            $query->where(function (Builder $q) use ($operator, $like, $search): void {
                $q->where('name', $operator, $like)
                    ->orWhere('email_normalized', 'like', '%'.mb_strtolower($search).'%')
                    ->orWhere('phone', 'like', $like);
            });
        }

        return response()->json([
            'data' => $query->get()->map(fn (TalentCandidate $c) => $c->toSearchResult())->values(),
        ]);
    }
}
