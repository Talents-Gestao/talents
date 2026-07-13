<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\Ferias;

use App\Enums\EmployeeLeaveStatus;
use App\Http\Controllers\Concerns\ResolvesFeriasRoutes;
use App\Models\CompanyEmployee;
use App\Models\EmployeeLeave;
use App\Support\Ferias\FeriasCompanyContext;
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
            return Inertia::render('Client/Ferias/Index', [
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
            $query->whereHas(
                'employee',
                fn ($q) => $q
                    ->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$term]),
            );
        }

        $leaves = $query->paginate(20)->withQueryString()->through(fn (EmployeeLeave $leave) => [
            'id' => $leave->id,
            'start_date' => $leave->start_date?->toDateString(),
            'end_date' => $leave->end_date?->toDateString(),
            'days' => $leave->daysCount(),
            'status' => $leave->status->value,
            'status_label' => $leave->status->label(),
            'notes' => $leave->notes,
            'employee' => $leave->employee?->only(['id', 'name', 'email']),
            'creator' => $leave->creator?->only(['id', 'name']),
        ]);

        return Inertia::render('Client/Ferias/Index', [
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

        return Inertia::render('Client/Ferias/Form', [
            'mode' => 'create',
            'leave' => null,
            ...$this->formOptions($company),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->company($request);
        $data = $this->validated($request, $company);

        EmployeeLeave::query()->create([
            'company_id' => $company->id,
            'company_employee_id' => $data['company_employee_id'],
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

        return Inertia::render('Client/Ferias/Form', [
            'mode' => 'edit',
            'leave' => [
                'id' => $leave->id,
                'company_employee_id' => $leave->company_employee_id,
                'start_date' => $leave->start_date?->toDateString(),
                'end_date' => $leave->end_date?->toDateString(),
                'status' => $leave->status->value,
                'notes' => $leave->notes,
            ],
            ...$this->formOptions($company),
        ]);
    }

    public function update(Request $request, EmployeeLeave $leave): RedirectResponse
    {
        $company = $this->company($request);
        $this->authorizeLeave($company, $leave);
        $data = $this->validated($request, $company);

        $leave->update([
            'company_employee_id' => $data['company_employee_id'],
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
     * @return array{employees: \Illuminate\Support\Collection<int, array{id: int, name: string}>, statusOptions: list<array{value: string, label: string}>}
     */
    private function formOptions(\App\Models\Company $company): array
    {
        return [
            'employees' => CompanyEmployee::query()
                ->where('company_id', $company->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'statusOptions' => $this->statusOptions(),
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
     * @return array{company_employee_id: int, start_date: string, end_date: string, status: string, notes: ?string}
     */
    private function validated(Request $request, \App\Models\Company $company): array
    {
        $data = $request->validate([
            'company_employee_id' => [
                'required',
                'integer',
                Rule::exists('company_employees', 'id')->where(fn ($q) => $q->where('company_id', $company->id)),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::enum(EmployeeLeaveStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $data['status'] = EmployeeLeaveStatus::from($data['status']);

        return $data;
    }

    private function authorizeLeave(\App\Models\Company $company, EmployeeLeave $leave): void
    {
        abort_unless($leave->company_id === $company->id, 403);
    }
}
