<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Enums\EmployeeLeaveStatus;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\EmployeeLeave;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeriasModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_crud_leave(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Férias',
            'ferias_access' => true,
            'is_active' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $admin = \App\Models\User::factory()->companyAdmin($company->id)->create();

        $employee = CompanyEmployee::query()->create([
            'company_id' => $company->id,
            'name' => 'Colaborador Férias',
            'email' => 'colab-ferias@teste.local',
            'is_active' => true,
        ]);

        $this->withoutVite();

        $this->actingAs($admin)
            ->get(route('client.ferias.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('client.ferias.store'), [
                'company_employee_id' => $employee->id,
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-15',
                'status' => EmployeeLeaveStatus::Scheduled->value,
                'notes' => 'Primeira parcela',
            ])
            ->assertRedirect(route('client.ferias.index'));

        $leave = EmployeeLeave::query()->first();
        $this->assertNotNull($leave);
        $this->assertSame(15, $leave->daysCount());

        $this->actingAs($admin)
            ->put(route('client.ferias.update', $leave), [
                'company_employee_id' => $employee->id,
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-20',
                'status' => EmployeeLeaveStatus::InProgress->value,
                'notes' => 'Atualizado',
            ])
            ->assertRedirect(route('client.ferias.index'));

        $this->assertSame(EmployeeLeaveStatus::InProgress, $leave->fresh()->status);

        $this->actingAs($admin)
            ->delete(route('client.ferias.destroy', $leave))
            ->assertRedirect(route('client.ferias.index'));

        $this->assertDatabaseMissing('employee_leaves', ['id' => $leave->id]);
    }

    public function test_company_user_cannot_access_ferias(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Férias',
            'ferias_access' => true,
            'is_active' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $user = \App\Models\User::factory()->companyUser($company->id)->create();

        $this->actingAs($user)
            ->get(route('client.ferias.index'))
            ->assertForbidden();
    }
}
