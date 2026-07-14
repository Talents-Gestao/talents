<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\Desligamento;

use App\Enums\ExitInterviewStatus;
use App\Http\Controllers\Concerns\ResolvesDesligamentoRoutes;
use App\Models\Company;
use App\Models\ExitInterview;
use App\Support\Desligamento\DesligamentoCompanyContext;
use App\Support\Desligamento\ExitInterviewScript;
use App\Support\Rhid\RhidPersonDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExitInterviewController extends DesligamentoCompanyController
{
    use ResolvesDesligamentoRoutes;

    public function index(Request $request): Response|RedirectResponse
    {
        $context = app(DesligamentoCompanyContext::class);

        if ($context->isAdminContext($request) && $request->routeIs('admin.desligamento.index')) {
            return redirect()->route('admin.survey-templates.index');
        }

        if ($context->needsCompanySelection($request)) {
            return Inertia::render('Client/Desligamento/Index', [
                'interviews' => ['data' => [], 'links' => []],
                'companyPicker' => $context->availableCompanies(),
                'activeCompany' => null,
                'isAdminContext' => true,
                'filters' => [
                    'q' => '',
                    'status' => '',
                ],
                'statusOptions' => $this->statusOptions(),
            ]);
        }

        $company = $this->company($request);

        $query = ExitInterview::query()
            ->where('company_id', $company->id)
            ->with(['employee:id,name,email', 'creator:id,name'])
            ->orderByDesc('interview_date')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('q')) {
            $term = '%'.mb_strtolower($request->string('q')->toString()).'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(COALESCE(employee_name, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(employee_email, \'\')) LIKE ?', [$term])
                    ->orWhereHas(
                        'employee',
                        fn ($eq) => $eq
                            ->whereRaw('LOWER(name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(email) LIKE ?', [$term]),
                    );
            });
        }

        $interviews = $query->paginate(20)->withQueryString()->through(fn (ExitInterview $interview) => [
            'id' => $interview->id,
            'interview_date' => $interview->interview_date?->toDateString(),
            'status' => $interview->status->value,
            'status_label' => $interview->status->label(),
            'employee' => $interview->collaboratorPayload(),
            'creator' => $interview->creator?->only(['id', 'name']),
        ]);

        return Inertia::render('Client/Desligamento/Index', [
            'interviews' => $interviews,
            'companyPicker' => $context->isAdminContext($request) ? $context->availableCompanies() : null,
            'activeCompany' => $company->only(['id', 'name']),
            'isAdminContext' => $context->isAdminContext($request),
            'filters' => [
                'q' => $request->string('q')->toString(),
                'status' => $request->string('status')->toString(),
            ],
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function create(Request $request): Response
    {
        $company = $this->company($request);

        return Inertia::render('Client/Desligamento/Form', [
            'mode' => 'create',
            'interview' => null,
            ...$this->formOptions($company, $request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->company($request);
        $data = $this->validated($request, $company);

        ExitInterview::query()->create([
            'company_id' => $company->id,
            'company_employee_id' => null,
            'rhid_person_id' => $data['rhid_person_id'],
            'employee_name' => $data['employee_name'],
            'employee_email' => $data['employee_email'],
            'interview_date' => $data['interview_date'],
            'status' => $data['status'],
            'answers' => $data['answers'],
            'consultant_notes' => $data['consultant_notes'],
            'created_by' => $request->user()?->id,
        ]);

        return $this->desligamentoRedirect('index', message: 'Pesquisa de desligamento salva.');
    }

    public function show(Request $request, ExitInterview $interview): Response
    {
        $company = $this->company($request);
        $this->authorizeInterview($company, $interview);

        $interview->load(['employee:id,name,email', 'creator:id,name']);

        return Inertia::render('Client/Desligamento/Show', [
            'interview' => [
                'id' => $interview->id,
                'interview_date' => $interview->interview_date?->toDateString(),
                'status' => $interview->status->value,
                'status_label' => $interview->status->label(),
                'answers' => $interview->answers ?? [],
                'consultant_notes' => $interview->consultant_notes ?? [],
                'employee' => $interview->collaboratorPayload(),
                'creator' => $interview->creator?->only(['id', 'name']),
            ],
            'sections' => ExitInterviewScript::sections(),
            'consultantNoteFields' => ExitInterviewScript::consultantNoteFields(),
        ]);
    }

    public function edit(Request $request, ExitInterview $interview): Response
    {
        $company = $this->company($request);
        $this->authorizeInterview($company, $interview);

        return Inertia::render('Client/Desligamento/Form', [
            'mode' => 'edit',
            'interview' => [
                'id' => $interview->id,
                'rhid_person_id' => $interview->rhid_person_id,
                'interview_date' => $interview->interview_date?->toDateString(),
                'status' => $interview->status->value,
                'answers' => $interview->answers ?? [],
                'consultant_notes' => $interview->consultant_notes ?? [],
            ],
            ...$this->formOptions($company, $request),
        ]);
    }

    public function update(Request $request, ExitInterview $interview): RedirectResponse
    {
        $company = $this->company($request);
        $this->authorizeInterview($company, $interview);
        $data = $this->validated($request, $company);

        $interview->update([
            'company_employee_id' => null,
            'rhid_person_id' => $data['rhid_person_id'],
            'employee_name' => $data['employee_name'],
            'employee_email' => $data['employee_email'],
            'interview_date' => $data['interview_date'],
            'status' => $data['status'],
            'answers' => $data['answers'],
            'consultant_notes' => $data['consultant_notes'],
        ]);

        return $this->desligamentoRedirect('index', message: 'Pesquisa de desligamento atualizada.');
    }

    public function destroy(Request $request, ExitInterview $interview): RedirectResponse
    {
        $company = $this->company($request);
        $this->authorizeInterview($company, $interview);

        $interview->delete();

        return $this->desligamentoRedirect('index', message: 'Pesquisa de desligamento removida.');
    }

    /**
     * @return array{
     *   employees: \Illuminate\Support\Collection<int, array{id: int, name: string, email: ?string}>,
     *   rhidReady: bool,
     *   statusOptions: list<array{value: string, label: string}>,
     *   sections: list<array{key: string, title: string, questions: list<array{key: string, body: string, hint?: string}>}>,
     *   consultantNoteFields: list<array{key: string, label: string}>
     * }
     */
    private function formOptions(Company $company, Request $request): array
    {
        $directory = app(RhidPersonDirectory::class);

        return [
            'employees' => $directory->activePersons($company, $request->user()),
            'rhidReady' => $company->hasRhidEnabled() && $company->rhidConfigured(),
            'statusOptions' => $this->statusOptions(),
            'sections' => ExitInterviewScript::sections(),
            'consultantNoteFields' => ExitInterviewScript::consultantNoteFields(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            fn (ExitInterviewStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            ExitInterviewStatus::all(),
        );
    }

    /**
     * @return array{
     *   rhid_person_id: int,
     *   employee_name: string,
     *   employee_email: ?string,
     *   interview_date: ?string,
     *   status: ExitInterviewStatus,
     *   answers: array<string, string>|null,
     *   consultant_notes: array<string, string>|null
     * }
     */
    private function validated(Request $request, Company $company): array
    {
        $answerRules = [];
        foreach (ExitInterviewScript::answerKeys() as $key) {
            $answerRules["answers.{$key}"] = ['nullable', 'string', 'max:20000'];
        }

        $noteRules = [];
        foreach (ExitInterviewScript::consultantNoteKeys() as $key) {
            $noteRules["consultant_notes.{$key}"] = ['nullable', 'string', 'max:20000'];
        }

        $data = $request->validate([
            'rhid_person_id' => ['required', 'integer', 'min:1'],
            'interview_date' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(ExitInterviewStatus::class)],
            'answers' => ['nullable', 'array'],
            'consultant_notes' => ['nullable', 'array'],
            ...$answerRules,
            ...$noteRules,
        ], [
            'rhid_person_id.required' => 'Selecione um colaborador do RHID.',
        ], [
            'rhid_person_id' => 'colaborador',
        ]);

        $person = app(RhidPersonDirectory::class)->findActive(
            $company,
            (int) $data['rhid_person_id'],
            $request->user(),
        );
        abort_unless($person !== null, 422, 'Colaborador não encontrado nos ativos do RHID/Control iD.');

        $answers = [];
        foreach (ExitInterviewScript::answerKeys() as $key) {
            $value = trim((string) ($data['answers'][$key] ?? ''));
            if ($value !== '') {
                $answers[$key] = $value;
            }
        }

        $notes = [];
        foreach (ExitInterviewScript::consultantNoteKeys() as $key) {
            $value = trim((string) ($data['consultant_notes'][$key] ?? ''));
            if ($value !== '') {
                $notes[$key] = $value;
            }
        }

        return [
            'rhid_person_id' => $person['id'],
            'employee_name' => $person['name'],
            'employee_email' => $person['email'],
            'interview_date' => $data['interview_date'] ?? null,
            'status' => ExitInterviewStatus::from($data['status']),
            'answers' => $answers === [] ? null : $answers,
            'consultant_notes' => $notes === [] ? null : $notes,
        ];
    }

    private function authorizeInterview(Company $company, ExitInterview $interview): void
    {
        abort_unless($interview->company_id === $company->id, 403);
    }
}
