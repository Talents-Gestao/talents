<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\Feedback;

use App\Http\Controllers\Concerns\ResolvesFeedbackRoutes;
use App\Models\CompanyEmployee;
use App\Models\Department;
use App\Models\FeedbackSession;
use App\Models\Position;
use App\Models\User;
use App\Support\Feedback\FeedbackVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeedbackEmployeeController extends FeedbackCompanyController
{
    use ResolvesFeedbackRoutes;
    public function index(Request $request): Response
    {
        $company = $this->company($request);

        $employees = FeedbackVisibility::scopeEmployees(
            CompanyEmployee::query()->where('company_id', $company->id),
            $request->user(),
        )
            ->with(['department', 'position', 'leader'])
            ->orderBy('name')
            ->paginate(20);

        return Inertia::render('Client/Feedbacks/Employees/Index', [
            'employees' => $employees,
        ]);
    }

    public function create(Request $request): Response
    {
        $company = $this->company($request);

        return Inertia::render('Client/Feedbacks/Employees/Form', [
            'mode' => 'create',
            'employee' => null,
            ...$this->formOptions($company),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->company($request);
        $data = $this->validated($request, $company);

        CompanyEmployee::create([
            'company_id' => $company->id,
            ...$data,
        ]);

        return $this->feedbackRedirect('employees.index', message: 'Colaborador cadastrado.');
    }

    public function show(Request $request, CompanyEmployee $employee): Response
    {
        $company = $this->company($request);
        FeedbackVisibility::authorizeEmployee($request->user(), $employee);

        $employee->load(['department', 'position', 'leader']);
        $sessionsQuery = FeedbackSession::query()
            ->where('company_employee_id', $employee->id)
            ->with(['leader', 'template'])
            ->orderByDesc('id');

        $sessions = FeedbackVisibility::scopeSessions($sessionsQuery, $request->user())->get();

        return Inertia::render('Client/Feedbacks/Employees/Show', [
            'employee' => $employee,
            'sessions' => $sessions,
        ]);
    }

    public function edit(Request $request, CompanyEmployee $employee): Response
    {
        $company = $this->company($request);
        FeedbackVisibility::authorizeEmployee($request->user(), $employee);

        return Inertia::render('Client/Feedbacks/Employees/Form', [
            'mode' => 'edit',
            'employee' => $employee->load(['department', 'position', 'leader']),
            ...$this->formOptions($company),
        ]);
    }

    public function update(Request $request, CompanyEmployee $employee): RedirectResponse
    {
        $company = $this->company($request);
        FeedbackVisibility::authorizeEmployee($request->user(), $employee);

        $employee->update($this->validated($request, $company));

        return $this->feedbackRedirect('employees.show', $employee, 'Colaborador atualizado.');
    }

    public function destroy(Request $request, CompanyEmployee $employee): RedirectResponse
    {
        $company = $this->company($request);
        FeedbackVisibility::authorizeEmployee($request->user(), $employee);

        $employee->delete();

        return $this->feedbackRedirect('employees.index', message: 'Colaborador removido.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(\App\Models\Company $company): array
    {
        return [
            'departments' => Department::query()->where('company_id', $company->id)->orderBy('name')->get(['id', 'name']),
            'positions' => Position::query()->where('company_id', $company->id)->orderBy('name')->get(['id', 'name']),
            'leaders' => User::query()
                ->where('company_id', $company->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, \App\Models\Company $company): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'leader_user_id' => ['nullable', 'exists:users,id'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if (! empty($data['department_id'])) {
            abort_unless(
                Department::query()->where('company_id', $company->id)->whereKey($data['department_id'])->exists(),
                422,
            );
        }
        if (! empty($data['position_id'])) {
            abort_unless(
                Position::query()->where('company_id', $company->id)->whereKey($data['position_id'])->exists(),
                422,
            );
        }
        if (! empty($data['leader_user_id'])) {
            abort_unless(
                User::query()->where('company_id', $company->id)->whereKey($data['leader_user_id'])->exists(),
                422,
            );
        }

        return $data;
    }
}
