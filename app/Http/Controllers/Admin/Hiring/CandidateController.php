<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hiring;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Enums\TalentCandidateAuditAction;
use App\Http\Controllers\Controller;
use App\Models\CompanyEmployee;
use App\Models\EmploymentCycle;
use App\Models\HiringApplication;
use App\Models\HiringApplicationEvent;
use App\Models\Interview;
use App\Models\TalentCandidate;
use App\Models\TalentCandidateAuditLog;
use App\Models\User;
use App\Services\Hiring\TalentCandidateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Cadastro de candidatos (Contratação · Candidatos). Não há exclusão definitiva: excluir = arquivar. */
class CandidateController extends Controller
{
    public const STATUS_FILTERS = ['active', 'archived', 'all'];

    public function __construct(
        private readonly TalentCandidateService $service,
    ) {}

    public function index(Request $request): Response
    {
        $status = in_array($request->query('status'), self::STATUS_FILTERS, true) ? $request->query('status') : 'active';
        $search = trim($request->string('q')->toString());

        $query = TalentCandidate::query()
            ->withCount('applications')
            ->orderBy('name')
            ->orderBy('id');

        if ($status === 'active') {
            $query->whereNull('archived_at');
        } elseif ($status === 'archived') {
            $query->whereNotNull('archived_at');
        }

        if ($search !== '') {
            $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $like = '%'.$search.'%';
            $digits = preg_replace('/\D+/', '', $search) ?? '';
            $query->where(function (Builder $q) use ($operator, $like, $search, $digits): void {
                $q->where('name', $operator, $like)
                    ->orWhere('email_normalized', 'like', '%'.mb_strtolower($search).'%');
                if (strlen($digits) >= 3) {
                    $q->orWhere('phone_digits', 'like', '%'.$digits.'%');
                }
            });
        }

        $candidates = $query->paginate(20)->withQueryString()->through(fn (TalentCandidate $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'email' => $c->email,
            'phone' => $c->phone,
            'current_role' => $c->current_role,
            'current_company' => $c->current_company,
            'source' => $c->source,
            'archived' => $c->isArchived(),
            'applications_count' => (int) $c->applications_count,
        ]);

        return Inertia::render('Admin/Hiring/Candidates/Index', [
            'candidates' => $candidates,
            'filters' => [
                'q' => $search !== '' ? $search : null,
                'status' => $status,
            ],
            'can' => $this->abilities($request->user()),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Hiring/Candidates/Form', [
            'candidate' => null,
            'contact_preferences' => $this->contactPreferences(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $candidate = $this->service->create($data, $request->user());

        return redirect()
            ->route('admin.contratacao.candidatos.show', $candidate)
            ->with('success', 'Candidato cadastrado.');
    }

    public function show(Request $request, TalentCandidate $candidate): Response
    {
        $user = $request->user();
        $can = $this->abilities($user);

        $applications = HiringApplication::query()
            ->where('talent_candidate_id', $candidate->id)
            ->with([
                'vacancy:id,title,status,company_id,hiring_process_id',
                'vacancy.company:id,name',
                'vacancy.stages:id,hiring_vacancy_id,name',
                'stage:id,name,is_hired',
                'events' => fn ($q) => $q->with('user:id,name'),
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (HiringApplication $a) use ($can): array {
                $stageNames = $a->vacancy?->stages->pluck('name', 'id') ?? collect();

                return [
                    'id' => $a->id,
                    'vacancy' => $a->vacancy ? [
                        'id' => $a->vacancy->id,
                        'title' => $a->vacancy->title,
                        'status_label' => $a->vacancy->status->label(),
                        'company_name' => $a->vacancy->company?->name,
                        'url' => $can['view_vacancies'] ? route('admin.contratacao.vagas.show', $a->vacancy) : null,
                    ] : null,
                    'stage_name' => $a->stage?->name,
                    'hired_at' => $a->hired_at?->toIso8601String(),
                    'created_at' => $a->created_at?->toIso8601String(),
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
                ];
            });

        $audit = TalentCandidateAuditLog::query()
            ->where('talent_candidate_id', $candidate->id)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (TalentCandidateAuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action->value,
                'action_label' => $log->action->label(),
                'user_name' => $log->user?->name,
                'created_at' => $log->created_at?->toIso8601String(),
                // Detalhes de anexo só para quem tem contratacao_anexos.
                'fields' => $log->action === TalentCandidateAuditAction::Updated ? ($log->meta['fields'] ?? []) : [],
                'attachment_name' => $log->action->isAttachment() && $can['view_attachments']
                    ? ($log->meta['original_name'] ?? null)
                    : null,
            ]);

        $canInterviews = (bool) $user?->canAccessAdmin(AdminPermissionModule::EntrevistasIa, PermissionAction::View);
        $interviews = Interview::query()
            ->where('talent_candidate_id', $candidate->id)
            ->with(['application.vacancy:id,title', 'questionnaire:id,name,version'])
            ->latest()
            ->get()
            ->map(fn (Interview $i) => [
                'id' => $i->id,
                'status_label' => $i->status->label(),
                'vacancy_title' => $i->application?->vacancy?->title,
                'questionnaire' => $i->questionnaire ? $i->questionnaire->name.' (v'.$i->questionnaire->version.')' : null,
                'is_reviewed' => $i->isReviewed(),
                'created_at' => $i->created_at?->toIso8601String(),
                'url' => $canInterviews ? route('admin.entrevistas.show', $i) : null,
            ]);

        // Vínculos de pós-contratação (pela candidatura ou pelo cadastro do colaborador ligado ao candidato).
        $canFollowup = (bool) $user?->canAccessAdmin(AdminPermissionModule::ContratacaoPos, PermissionAction::View);
        $employment = EmploymentCycle::query()
            ->where(function ($q) use ($candidate): void {
                $q->whereIn('hiring_application_id', HiringApplication::query()->select('id')->where('talent_candidate_id', $candidate->id))
                    ->orWhereIn('company_employee_id', CompanyEmployee::query()->select('id')->where('talent_candidate_id', $candidate->id));
            })
            ->with(['company:id,name', 'manager:id,name'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (EmploymentCycle $c) => [
                'id' => $c->id,
                'company_name' => $c->company?->name,
                'role_title' => $c->role_title,
                'manager_name' => $c->manager?->name,
                'status_label' => $c->status->label(),
                'admission_date' => $c->admission_date?->toDateString(),
                'url' => $canFollowup ? route('admin.contratacao.pos.show', $c) : null,
            ]);

        $props = [
            'employment' => $employment,
            'candidate' => $this->candidateToFrontend($candidate),
            'applications' => $applications,
            'interviews' => $interviews,
            'audit' => $audit,
            'contact_preferences' => $this->contactPreferences(),
            'can' => $can,
        ];

        if ($can['view_attachments']) {
            $props['attachments_count'] = $candidate->attachments()->count();
            $props['attachments_url'] = route('admin.contratacao.candidatos.anexos.index', $candidate);
        }

        return Inertia::render('Admin/Hiring/Candidates/Show', $props);
    }

    public function edit(Request $request, TalentCandidate $candidate): Response
    {
        return Inertia::render('Admin/Hiring/Candidates/Form', [
            'candidate' => $this->candidateToFrontend($candidate),
            'contact_preferences' => $this->contactPreferences(),
        ]);
    }

    public function update(Request $request, TalentCandidate $candidate): RedirectResponse
    {
        $data = $this->validated($request);
        $this->service->update($candidate, $data, $request->user());

        return redirect()
            ->route('admin.contratacao.candidatos.show', $candidate)
            ->with('success', 'Cadastro atualizado.');
    }

    public function archive(Request $request, TalentCandidate $candidate): RedirectResponse
    {
        $this->service->archive($candidate, $request->user());

        return back()->with('success', 'Cadastro arquivado. Candidaturas, entrevistas e anexos foram preservados.');
    }

    public function restore(Request $request, TalentCandidate $candidate): RedirectResponse
    {
        $this->service->restore($candidate, $request->user());

        return back()->with('success', 'Cadastro restaurado.');
    }

    /** «Cadastrar no Talents» a partir do Banco de talentos (Sólides). */
    public function importSolides(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'solides_id' => ['required', 'string', 'max:64'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        // E-mail inválido vindo da Sólides não bloqueia a importação: é descartado.
        if (! empty($data['email']) && filter_var(trim($data['email']), FILTER_VALIDATE_EMAIL) === false) {
            $data['email'] = null;
        }

        $result = $this->service->importFromSolides($data, $request->user());
        $candidate = $result['candidate'];

        $message = match (true) {
            $result['created'] => 'Candidato cadastrado a partir da Sólides.',
            $candidate->isArchived() => 'Este candidato já tem cadastro no Talents, mas está arquivado.',
            default => 'Este candidato já tinha cadastro no Talents; nenhum dado foi sobrescrito.',
        };

        return redirect()
            ->route('admin.contratacao.candidatos.show', $candidate)
            ->with($result['created'] ? 'success' : 'info', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! TalentCandidate::isValidInternationalPhone((string) $value)) {
                    $fail('Informe o telefone no formato internacional, começando com + e o código do país (ex.: +55 11 91234-5678).');
                }
            }],
            'email_reviewed' => ['boolean'],
            'phone_reviewed' => ['boolean'],
            'email_opt_in' => ['boolean'],
            'contact_preference' => ['nullable', Rule::in(TalentCandidate::CONTACT_PREFERENCES)],
            'source' => ['nullable', 'string', 'max:120'],
            'current_role' => ['nullable', 'string', 'max:255'],
            'current_company' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [], [
            'name' => 'nome',
            'email' => 'e-mail',
            'phone' => 'telefone',
            'contact_preference' => 'preferência de contato',
            'source' => 'origem',
            'current_role' => 'cargo atual',
            'current_company' => 'empresa atual',
            'notes' => 'observações',
        ]);

        foreach (['email_reviewed', 'phone_reviewed', 'email_opt_in'] as $flag) {
            $data[$flag] = (bool) ($data[$flag] ?? false);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function candidateToFrontend(TalentCandidate $c): array
    {
        return [
            'id' => $c->id,
            'solides_id' => $c->solides_id,
            'name' => $c->name,
            'email' => $c->email,
            'phone' => $c->phone,
            'email_reviewed' => $c->email_reviewed,
            'phone_reviewed' => $c->phone_reviewed,
            'email_opt_in' => $c->email_opt_in,
            'contact_preference' => $c->contact_preference,
            'source' => $c->source,
            'current_role' => $c->current_role,
            'current_company' => $c->current_company,
            'notes' => $c->notes,
            'archived' => $c->isArchived(),
            'archived_at' => $c->archived_at?->toIso8601String(),
            'created_at' => $c->created_at?->toIso8601String(),
            'updated_at' => $c->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function contactPreferences(): array
    {
        return array_map(
            static fn (string $v) => ['value' => $v, 'label' => TalentCandidate::contactPreferenceLabel($v)],
            TalentCandidate::CONTACT_PREFERENCES,
        );
    }

    /**
     * @return array<string, bool>
     */
    private function abilities(?User $user): array
    {
        $can = static fn (AdminPermissionModule $m, PermissionAction $a): bool => (bool) $user?->canAccessAdmin($m, $a);

        return [
            'create' => $can(AdminPermissionModule::ContratacaoCandidatos, PermissionAction::Create),
            'edit' => $can(AdminPermissionModule::ContratacaoCandidatos, PermissionAction::Edit),
            'archive' => $can(AdminPermissionModule::ContratacaoCandidatos, PermissionAction::Delete),
            'view_attachments' => $can(AdminPermissionModule::ContratacaoAnexos, PermissionAction::View),
            'view_vacancies' => $can(AdminPermissionModule::ContratacaoVagas, PermissionAction::View),
        ];
    }
}
