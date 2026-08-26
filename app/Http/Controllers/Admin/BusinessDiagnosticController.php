<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessDiagnostic;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BusinessDiagnosticController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim($request->string('q')->toString());

        $diagnostics = BusinessDiagnostic::query()
            ->with(['company:id,name', 'creator:id,name'])
            ->when($q !== '', function ($query) use ($q) {
                $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $query->where(function ($inner) use ($q, $operator) {
                    $inner->where('company_name', $operator, '%'.$q.'%')
                        ->orWhere('cnpj', $operator, '%'.$q.'%')
                        ->orWhere('responsible_name', $operator, '%'.$q.'%')
                        ->orWhere('email', $operator, '%'.$q.'%');
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (BusinessDiagnostic $row) => $this->listPayload($row));

        return Inertia::render('Admin/BusinessDiagnostics/Index', [
            'diagnostics' => $diagnostics,
            'filters' => [
                'q' => $q !== '' ? $q : null,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $companyId = $request->integer('company_id') ?: null;

        $prefill = null;
        if ($companyId && $companyId > 0) {
            $company = Company::query()->find($companyId);
            if ($company) {
                $prefill = [
                    'company_id' => $company->id,
                    'company_name' => $company->name,
                    'cnpj' => $company->cnpj,
                    'segment' => $company->segment,
                    'employee_count' => $company->employee_count_estimate !== null
                        ? (string) $company->employee_count_estimate
                        : null,
                    'email' => $company->contact_email,
                ];
            }
        }

        return Inertia::render('Admin/BusinessDiagnostics/Form', [
            'mode' => 'create',
            'diagnostic' => null,
            'prefill' => $prefill,
            'companies' => Company::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()?->id;

        $diagnostic = BusinessDiagnostic::query()->create($data);

        return redirect()
            ->route('admin.diagnostico-empresarial.show', $diagnostic)
            ->with('success', 'Diagnóstico empresarial registrado.');
    }

    public function show(BusinessDiagnostic $diagnostico_empresarial): Response
    {
        $diagnostico_empresarial->load(['company:id,name', 'creator:id,name']);

        return Inertia::render('Admin/BusinessDiagnostics/Show', [
            'diagnostic' => $this->detailPayload($diagnostico_empresarial),
        ]);
    }

    public function edit(BusinessDiagnostic $diagnostico_empresarial): Response
    {
        return Inertia::render('Admin/BusinessDiagnostics/Form', [
            'mode' => 'edit',
            'diagnostic' => $this->formPayload($diagnostico_empresarial),
            'prefill' => null,
            'companies' => Company::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, BusinessDiagnostic $diagnostico_empresarial): RedirectResponse
    {
        $diagnostico_empresarial->update($this->validated($request));

        return redirect()
            ->route('admin.diagnostico-empresarial.show', $diagnostico_empresarial)
            ->with('success', 'Diagnóstico empresarial atualizado.');
    }

    public function destroy(BusinessDiagnostic $diagnostico_empresarial): RedirectResponse
    {
        $diagnostico_empresarial->delete();

        return redirect()
            ->route('admin.diagnostico-empresarial.index')
            ->with('success', 'Diagnóstico empresarial removido.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'company_id' => ['nullable', 'exists:companies,id'],
            'company_name' => ['required', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:18'],
            'segment' => ['nullable', 'string', 'max:120'],
            'employee_count' => ['nullable', 'string', 'max:60'],
            'responsible_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'company_history' => ['nullable', 'string', 'max:10000'],
            'biggest_challenge' => ['nullable', 'string', 'max:10000'],
            'hr_maturity' => ['nullable', 'integer', 'between:1,10'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function listPayload(BusinessDiagnostic $row): array
    {
        return [
            'id' => $row->id,
            'company_name' => $row->company_name,
            'cnpj' => $row->cnpj,
            'responsible_name' => $row->responsible_name,
            'email' => $row->email,
            'phone' => $row->phone,
            'hr_maturity' => $row->hr_maturity,
            'company' => $row->company ? ['id' => $row->company->id, 'name' => $row->company->name] : null,
            'creator' => $row->creator ? ['id' => $row->creator->id, 'name' => $row->creator->name] : null,
            'created_at' => $row->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formPayload(BusinessDiagnostic $row): array
    {
        return [
            'id' => $row->id,
            'company_id' => $row->company_id,
            'company_name' => $row->company_name,
            'cnpj' => $row->cnpj,
            'segment' => $row->segment,
            'employee_count' => $row->employee_count,
            'responsible_name' => $row->responsible_name,
            'email' => $row->email,
            'phone' => $row->phone,
            'company_history' => $row->company_history,
            'biggest_challenge' => $row->biggest_challenge,
            'hr_maturity' => $row->hr_maturity,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailPayload(BusinessDiagnostic $row): array
    {
        return [
            ...$this->formPayload($row),
            'company' => $row->company ? ['id' => $row->company->id, 'name' => $row->company->name] : null,
            'creator' => $row->creator ? ['id' => $row->creator->id, 'name' => $row->creator->name] : null,
            'created_at' => $row->created_at?->toIso8601String(),
            'updated_at' => $row->updated_at?->toIso8601String(),
        ];
    }
}
