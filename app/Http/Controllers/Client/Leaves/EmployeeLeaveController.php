<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\Leaves;

use App\Actions\Company\ResolveOrCreateCompanyEmployee;
use App\Enums\EmployeeLeaveStatus;
use App\Http\Controllers\Concerns\ResolvesFeriasRoutes;
use App\Models\EmployeeLeave;
use App\Support\Company\CompanyEmployeeDirectory;
use App\Support\Leaves\FeriasCompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeLeaveController extends FeriasCompanyController
{
    use ResolvesFeriasRoutes;

    public function index(Request $request): Response
    {
        $context = app(FeriasCompanyContext::class);

        if ($context->needsCompanySelection($request)) {
            return Inertia::render('Client/Leaves/Index', [
                'leaves' => ['data' => [], 'links' => []],
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

        $query = EmployeeLeave::query()
            ->where('company_id', $company->id)
            ->with(['employee:id,name,email', 'creator:id,name'])
            ->orderByDesc('start_date')
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

        $leaves = $query->paginate(20)->withQueryString()->through(fn (EmployeeLeave $leave) => [
            'id' => $leave->id,
            'start_date' => $leave->start_date?->toDateString(),
            'end_date' => $leave->end_date?->toDateString(),
            'days' => $leave->daysCount(),
            'status' => $leave->status->value,
            'status_label' => $leave->status->label(),
            'notes' => $leave->notes,
            'employee' => $leave->collaboratorPayload(),
            'creator' => $leave->creator?->only(['id', 'name']),
        ]);

        return Inertia::render('Client/Leaves/Index', [
            'leaves' => $leaves,
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

        return Inertia::render('Client/Leaves/Form', [
            'mode' => 'create',
            'leave' => null,
            ...$this->formOptions($company, $request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->company($request);
        $data = $this->validated($request, $company);

        $employee = app(ResolveOrCreateCompanyEmployee::class)->execute(
            $company,
            $data['employee_name'],
            $data['employee_email'],
        );

        EmployeeLeave::query()->create([
            'company_id' => $company->id,
            'company_employee_id' => $employee->id,
            'rhid_person_id' => null,
            'employee_name' => $data['employee_name'],
            'employee_email' => $data['employee_email'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        return $this->feriasRedirect('index', message: 'Período de férias cadastrado.');
    }

    public function edit(Request $request, EmployeeLeave $leave): Response
    {
        $company = $this->company($request);
        $this->authorizeLeave($company, $leave);

        return Inertia::render('Client/Leaves/Form', [
            'mode' => 'edit',
            'leave' => [
                'id' => $leave->id,
                'employee_name' => $leave->employee_name,
                'employee_email' => $leave->employee_email,
                'start_date' => $leave->start_date?->toDateString(),
                'end_date' => $leave->end_date?->toDateString(),
                'status' => $leave->status->value,
                'notes' => $leave->notes,
            ],
            ...$this->formOptions($company, $request),
        ]);
    }

    public function update(Request $request, EmployeeLeave $leave): RedirectResponse
    {
        $company = $this->company($request);
        $this->authorizeLeave($company, $leave);
        $data = $this->validated($request, $company);

        $employee = app(ResolveOrCreateCompanyEmployee::class)->execute(
            $company,
            $data['employee_name'],
            $data['employee_email'],
        );

        $leave->update([
            'company_employee_id' => $employee->id,
            'rhid_person_id' => null,
            'employee_name' => $data['employee_name'],
            'employee_email' => $data['employee_email'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
        ]);

        return $this->feriasRedirect('index', message: 'Período de férias atualizado.');
    }

    public function destroy(Request $request, EmployeeLeave $leave): RedirectResponse
    {
        $company = $this->company($request);
        $this->authorizeLeave($company, $leave);

        $leave->delete();

        return $this->feriasRedirect('index', message: 'Período de férias removido.');
    }

    /**
     * @return array{
     *   statusOptions: list<array{value: string, label: string}>,
     *   employees: list<array{id: int, name: string, email: ?string}>
     * }
     */
    private function formOptions(\App\Models\Company $company, Request $request): array
    {
        return [
            'statusOptions' => $this->statusOptions(),
            'employees' => app(CompanyEmployeeDirectory::class)->suggestionsFor($company),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            fn (EmployeeLeaveStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            EmployeeLeaveStatus::all(),
        );
    }

    /**
     * @return array{
     *   employee_name: string,
     *   employee_email: ?string,
     *   start_date: string,
     *   end_date: string,
     *   status: EmployeeLeaveStatus,
     *   notes: ?string
     * }
     */
    private function validated(Request $request, \App\Models\Company $company): array
    {
        $data = $request->validate([
            'employee_name' => ['required', 'string', 'max:255'],
            'employee_email' => ['nullable', 'email', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::enum(EmployeeLeaveStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [], [
            'employee_name' => 'nome do colaborador',
            'employee_email' => 'e-mail do colaborador',
        ]);

        $employeeName = trim($data['employee_name']);
        $employeeEmail = isset($data['employee_email']) ? trim((string) $data['employee_email']) : '';

        return [
            'employee_name' => $employeeName,
            'employee_email' => $employeeEmail !== '' ? $employeeEmail : null,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status' => EmployeeLeaveStatus::from($data['status']),
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function authorizeLeave(\App\Models\Company $company, EmployeeLeave $leave): void
    {
        abort_unless($leave->company_id === $company->id, 403);
    }
}
