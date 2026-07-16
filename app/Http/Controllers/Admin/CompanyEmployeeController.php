<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use App\Services\ViaCepService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;

class CompanyEmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $companyId = $this->selectedCompanyId($request);
        $search = trim($request->string('q')->toString());

        $query = CompanyEmployee::query()
            ->with([
                'company:id,name',
                'department:id,name',
                'position:id,name',
                'leader:id,name',
            ])
            ->orderBy('name');

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($like, $operator) {
                $q->where('name', $operator, $like)
                    ->orWhere('email', $operator, $like)
                    ->orWhere('cpf', $operator, $like)
                    ->orWhere('phone', $operator, $like);
            });
        }

        $employees = $query->paginate(20)->withQueryString()->through(fn (CompanyEmployee $e) => $this->listPayload($e));

        return Inertia::render('Admin/Colaboradores/Index', [
            'employees' => $employees,
            'companies' => $this->companiesOptions(),
            'filters' => [
                'company_id' => $companyId,
                'q' => $search !== '' ? $search : null,
            ],
        ]);
    }

    public function lookupCep(Request $request, ViaCepService $viaCep): JsonResponse
    {
        $request->validate([
            'cep' => ['required', 'string', 'max:16'],
        ]);

        try {
            return response()->json($viaCep->lookup($request->string('cep')->toString()));
        } catch (InvalidArgumentException|RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function create(Request $request): Response
    {
        $companyId = $this->selectedCompanyId($request);
        abort_unless($companyId !== null, 422, 'Selecione uma empresa para cadastrar o colaborador.');

        $company = Company::query()->findOrFail($companyId);

        return Inertia::render('Admin/Colaboradores/Form', [
            'mode' => 'create',
            'employee' => null,
            'company' => $company->only(['id', 'name']),
            ...$this->formOptions($company),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $company = Company::query()->findOrFail((int) $data['company_id']);

        $employee = CompanyEmployee::query()->create([
            ...$this->attributesFromValidated($data),
            'company_id' => $company->id,
        ]);

        return redirect()
            ->route('admin.colaboradores.show', $employee)
            ->with('success', 'Colaborador cadastrado.');
    }

    public function show(CompanyEmployee $employee): Response
    {
        $employee->load([
            'company:id,name',
            'department:id,name',
            'position:id,name',
            'leader:id,name,email',
        ]);

        return Inertia::render('Admin/Colaboradores/Show', [
            'employee' => $this->detailPayload($employee),
        ]);
    }

    public function edit(CompanyEmployee $employee): Response
    {
        $employee->load(['company:id,name']);

        return Inertia::render('Admin/Colaboradores/Form', [
            'mode' => 'edit',
            'employee' => $this->formPayload($employee),
            'company' => $employee->company?->only(['id', 'name']),
            ...$this->formOptions($employee->company),
        ]);
    }

    public function update(Request $request, CompanyEmployee $employee): RedirectResponse
    {
        $data = $this->validated($request, $employee);
        $employee->update($this->attributesFromValidated($data));

        return redirect()
            ->route('admin.colaboradores.show', $employee)
            ->with('success', 'Ficha do colaborador atualizada.');
    }

    public function destroy(CompanyEmployee $employee): RedirectResponse
    {
        $companyId = $employee->company_id;
        $employee->delete();

        return redirect()
            ->route('admin.colaboradores.index', ['company_id' => $companyId])
            ->with('success', 'Colaborador removido.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?CompanyEmployee $employee = null): array
    {
        $companyId = (int) ($request->input('company_id') ?: $employee?->company_id);

        return $request->validate([
            'company_id' => [$employee ? 'sometimes' : 'required', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('company_employees', 'email')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($employee?->id),
            ],
            'birth_date' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address_zip' => ['nullable', 'string', 'max:16'],
            'address_street' => ['nullable', 'string', 'max:255'],
            'address_number' => ['nullable', 'string', 'max:32'],
            'address_complement' => ['nullable', 'string', 'max:120'],
            'address_neighborhood' => ['nullable', 'string', 'max:120'],
            'address_city' => ['nullable', 'string', 'max:120'],
            'address_state' => ['nullable', 'string', 'size:2'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:32'],
            'department_id' => [
                'nullable',
                Rule::exists('departments', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'position_id' => [
                'nullable',
                Rule::exists('positions', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'leader_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'admission_date' => ['nullable', 'date'],
            'work_schedule' => ['nullable', 'string', 'max:255'],
            'cpf' => ['nullable', 'string', 'max:14'],
            'rg' => ['nullable', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [], [
            'name' => 'nome completo',
            'email' => 'e-mail',
            'birth_date' => 'data de nascimento',
            'phone' => 'telefone',
            'address_zip' => 'CEP',
            'address_street' => 'rua',
            'address_number' => 'número',
            'address_complement' => 'complemento',
            'address_neighborhood' => 'bairro',
            'address_city' => 'cidade',
            'address_state' => 'UF',
            'emergency_contact_name' => 'nome do contato de emergência',
            'emergency_contact_relationship' => 'parentesco',
            'emergency_contact_phone' => 'telefone do contato de emergência',
            'department_id' => 'setor',
            'position_id' => 'cargo',
            'leader_user_id' => 'gestor responsável',
            'admission_date' => 'data de admissão',
            'work_schedule' => 'jornada de trabalho',
            'cpf' => 'CPF',
            'rg' => 'RG',
            'notes' => 'observações',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributesFromValidated(array $data): array
    {
        $email = isset($data['email']) ? trim((string) $data['email']) : '';

        return [
            'name' => trim((string) $data['name']),
            'email' => $email !== '' ? $email : null,
            'birth_date' => $data['birth_date'] ?? null,
            'phone' => $this->nullableTrim($data['phone'] ?? null),
            'address_zip' => $this->nullableTrim($data['address_zip'] ?? null),
            'address_street' => $this->nullableTrim($data['address_street'] ?? null),
            'address_number' => $this->nullableTrim($data['address_number'] ?? null),
            'address_complement' => $this->nullableTrim($data['address_complement'] ?? null),
            'address_neighborhood' => $this->nullableTrim($data['address_neighborhood'] ?? null),
            'address_city' => $this->nullableTrim($data['address_city'] ?? null),
            'address_state' => ($state = strtoupper($this->nullableTrim($data['address_state'] ?? null) ?? '')) !== ''
                ? $state
                : null,
            'emergency_contact_name' => $this->nullableTrim($data['emergency_contact_name'] ?? null),
            'emergency_contact_relationship' => $this->nullableTrim($data['emergency_contact_relationship'] ?? null),
            'emergency_contact_phone' => $this->nullableTrim($data['emergency_contact_phone'] ?? null),
            'department_id' => ($data['department_id'] ?? null) ?: null,
            'position_id' => ($data['position_id'] ?? null) ?: null,
            'leader_user_id' => ($data['leader_user_id'] ?? null) ?: null,
            'admission_date' => $data['admission_date'] ?? null,
            'work_schedule' => $this->nullableTrim($data['work_schedule'] ?? null),
            'cpf' => $this->nullableTrim($data['cpf'] ?? null),
            'rg' => $this->nullableTrim($data['rg'] ?? null),
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'notes' => $this->nullableTrim($data['notes'] ?? null),
        ];
    }

    private function nullableTrim(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : null;
    }

    private function selectedCompanyId(Request $request): ?int
    {
        $companyId = $request->integer('company_id') ?: null;

        return ($companyId !== null && $companyId > 0) ? $companyId : null;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function companiesOptions(): array
    {
        return Company::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Company $c) => ['id' => $c->id, 'name' => $c->name])
            ->all();
    }

    /**
     * @return array{
     *   departments: list<array{id: int, name: string}>,
     *   positions: list<array{id: int, name: string}>,
     *   leaders: list<array{id: int, name: string, email: ?string}>
     * }
     */
    private function formOptions(?Company $company): array
    {
        if ($company === null) {
            return [
                'departments' => [],
                'positions' => [],
                'leaders' => [],
            ];
        }

        return [
            'departments' => Department::query()
                ->where('company_id', $company->id)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Department $d) => ['id' => $d->id, 'name' => $d->name])
                ->all(),
            'positions' => Position::query()
                ->where('company_id', $company->id)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Position $p) => ['id' => $p->id, 'name' => $p->name])
                ->all(),
            'leaders' => User::query()
                ->where('company_id', $company->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listPayload(CompanyEmployee $e): array
    {
        return [
            'id' => $e->id,
            'name' => $e->name,
            'email' => $e->email,
            'phone' => $e->phone,
            'cpf' => $e->cpf,
            'is_active' => $e->is_active,
            'company' => $e->company ? ['id' => $e->company->id, 'name' => $e->company->name] : null,
            'department' => $e->department ? ['id' => $e->department->id, 'name' => $e->department->name] : null,
            'position' => $e->position ? ['id' => $e->position->id, 'name' => $e->position->name] : null,
            'leader' => $e->leader ? ['id' => $e->leader->id, 'name' => $e->leader->name] : null,
            'admission_date' => $e->admission_date?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formPayload(CompanyEmployee $e): array
    {
        return [
            'id' => $e->id,
            'company_id' => $e->company_id,
            'name' => $e->name,
            'email' => $e->email,
            'birth_date' => $e->birth_date?->toDateString(),
            'phone' => $e->phone,
            'address_zip' => $e->address_zip,
            'address_street' => $e->address_street,
            'address_number' => $e->address_number,
            'address_complement' => $e->address_complement,
            'address_neighborhood' => $e->address_neighborhood,
            'address_city' => $e->address_city,
            'address_state' => $e->address_state,
            'emergency_contact_name' => $e->emergency_contact_name,
            'emergency_contact_relationship' => $e->emergency_contact_relationship,
            'emergency_contact_phone' => $e->emergency_contact_phone,
            'department_id' => $e->department_id,
            'position_id' => $e->position_id,
            'leader_user_id' => $e->leader_user_id,
            'admission_date' => $e->admission_date?->toDateString(),
            'work_schedule' => $e->work_schedule,
            'cpf' => $e->cpf,
            'rg' => $e->rg,
            'is_active' => $e->is_active,
            'notes' => $e->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailPayload(CompanyEmployee $e): array
    {
        return [
            ...$this->formPayload($e),
            'company' => $e->company ? ['id' => $e->company->id, 'name' => $e->company->name] : null,
            'department' => $e->department ? ['id' => $e->department->id, 'name' => $e->department->name] : null,
            'position' => $e->position ? ['id' => $e->position->id, 'name' => $e->position->name] : null,
            'leader' => $e->leader ? [
                'id' => $e->leader->id,
                'name' => $e->leader->name,
                'email' => $e->leader->email,
            ] : null,
        ];
    }
}
