<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\EmployeeLeaveStatus;
use App\Models\Company;
use App\Models\EmployeeLeave;
use App\Models\User;
use App\Support\Leaves\FeriasCompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class FeriasCompanyShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_ferias_tab_falls_back_to_empresa(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = Company::query()->create([
            'name' => 'Empresa Detalhe Férias',
            'ferias_access' => true,
            'is_active' => true,
        ]);

        $this->withoutVite();

        $this->actingAs($admin)
            ->get(route('admin.companies.show', ['company' => $company->id, 'tab' => 'ferias']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Companies/Show')
                ->where('tab', 'empresa')
                ->missing('leaves')
            );
    }

    public function test_admin_create_leave_redirects_to_ferias_index(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = Company::query()->create([
            'name' => 'Empresa Create Férias',
            'ferias_access' => true,
            'is_active' => true,
        ]);

        $this->withoutVite();

        $this->actingAs($admin)
            ->withSession([FeriasCompanyContext::SESSION_KEY => $company->id])
            ->post(route('admin.ferias.store'), [
                'employee_name' => 'Bruno Costa',
                'employee_email' => 'bruno@teste.local',
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-10',
                'status' => EmployeeLeaveStatus::Scheduled->value,
                'notes' => 'Parcela 1',
            ])
            ->assertRedirect(route('admin.ferias.index'));

        $this->assertDatabaseHas('employee_leaves', [
            'company_id' => $company->id,
            'employee_name' => 'Bruno Costa',
        ]);
    }

    public function test_admin_ferias_index_lists_leaves_when_session_set(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = Company::query()->create([
            'name' => 'Empresa Redirect Férias',
            'ferias_access' => true,
            'is_active' => true,
        ]);

        EmployeeLeave::query()->create([
            'company_id' => $company->id,
            'employee_name' => 'Ana Silva',
            'employee_email' => 'ana@teste.local',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-15',
            'status' => EmployeeLeaveStatus::Scheduled,
            'created_by' => $admin->id,
        ]);

        $this->withoutVite();

        $this->actingAs($admin)
            ->withSession([FeriasCompanyContext::SESSION_KEY => $company->id])
            ->get(route('admin.ferias.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/Leaves/Index')
                ->where('isAdminContext', true)
                ->where('activeCompany.id', $company->id)
                ->has('leaves.data', 1)
                ->where('leaves.data.0.employee.name', 'Ana Silva')
            );
    }

    public function test_admin_ferias_index_without_company_shows_picker(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        Company::query()->create([
            'name' => 'Empresa Picker Férias',
            'ferias_access' => true,
            'is_active' => true,
        ]);

        $this->withoutVite();

        $this->actingAs($admin)
            ->get(route('admin.ferias.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/Leaves/Index')
                ->where('isAdminContext', true)
                ->where('activeCompany', null)
                ->has('companyPicker')
            );
    }
}
