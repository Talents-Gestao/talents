<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CompanyEmployeeAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_list_and_create_employee_ficha(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = Company::query()->create(['name' => 'Empresa Ficha', 'is_active' => true]);
        $department = Department::query()->create(['company_id' => $company->id, 'name' => 'RH']);
        $position = Position::query()->create(['company_id' => $company->id, 'name' => 'Analista']);

        $this->withoutVite();

        $this->actingAs($admin)
            ->get(route('admin.colaboradores.index', ['company_id' => $company->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Colaboradores/Index')
                ->has('companies'));

        $this->actingAs($admin)
            ->post(route('admin.colaboradores.store'), [
                'company_id' => $company->id,
                'name' => 'Maria Silva',
                'email' => 'maria@empresa.local',
                'birth_date' => '1990-05-10',
                'phone' => '(11) 98765-4321',
                'address' => 'Rua das Flores, 100',
                'emergency_contact_name' => 'João Silva',
                'emergency_contact_relationship' => 'Cônjuge',
                'emergency_contact_phone' => '(11) 91234-5678',
                'department_id' => $department->id,
                'position_id' => $position->id,
                'admission_date' => '2024-01-15',
                'work_schedule' => 'Segunda a sexta, 08h às 17h',
                'cpf' => '123.456.789-00',
                'rg' => '12.345.678-9',
                'is_active' => true,
                'notes' => 'Observação de teste.',
            ])
            ->assertRedirect();

        $employee = CompanyEmployee::query()->first();
        $this->assertNotNull($employee);
        $this->assertSame('Maria Silva', $employee->name);
        $this->assertSame('maria@empresa.local', $employee->email);
        $this->assertSame('Rua das Flores, 100', $employee->address);
        $this->assertSame('João Silva', $employee->emergency_contact_name);
        $this->assertSame('Cônjuge', $employee->emergency_contact_relationship);
        $this->assertSame('123.456.789-00', $employee->cpf);
        $this->assertSame($department->id, $employee->department_id);
        $this->assertSame($position->id, $employee->position_id);

        $this->actingAs($admin)
            ->get(route('admin.colaboradores.show', $employee))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Colaboradores/Show')
                ->where('employee.name', 'Maria Silva'));
    }

    public function test_admin_can_update_and_destroy_employee(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = Company::query()->create(['name' => 'Empresa Edit', 'is_active' => true]);
        $employee = CompanyEmployee::query()->create([
            'company_id' => $company->id,
            'name' => 'Nome Antigo',
            'email' => 'antigo@empresa.local',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.colaboradores.update', $employee), [
                'company_id' => $company->id,
                'name' => 'Nome Novo',
                'email' => 'novo@empresa.local',
                'is_active' => false,
                'notes' => 'Atualizado',
            ])
            ->assertRedirect(route('admin.colaboradores.show', $employee));

        $employee->refresh();
        $this->assertSame('Nome Novo', $employee->name);
        $this->assertFalse($employee->is_active);

        $this->actingAs($admin)
            ->delete(route('admin.colaboradores.destroy', $employee))
            ->assertRedirect();

        $this->assertDatabaseMissing('company_employees', ['id' => $employee->id]);
    }
}
