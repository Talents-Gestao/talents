<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompanyActivityFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_update_activity_branch_and_collective_bargaining_month(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Sindical',
            'cnpj' => '12.345.678/0001-90',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);

        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)
            ->put(route('admin.companies.update', $company), [
                'name' => $company->name,
                'activity_branch' => 'Comércio varejista',
                'collective_bargaining_month' => 7,
                'is_active' => true,
                'strategic_calendar_access_mode' => 'inherit',
                'tasks_access_mode' => 'inherit',
                'rhid_access_mode' => 'inherit',
                'denuncias_access_mode' => 'inherit',
            ])
            ->assertRedirect(route('admin.companies.show', $company));

        $company->refresh();

        $this->assertSame('Comércio varejista', $company->activity_branch);
        $this->assertSame(7, $company->collective_bargaining_month);
    }

    public function test_collective_bargaining_month_must_be_between_1_and_12(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Validação',
            'cnpj' => '98.765.432/0001-10',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);

        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)
            ->put(route('admin.companies.update', $company), [
                'name' => $company->name,
                'collective_bargaining_month' => 13,
                'is_active' => true,
                'strategic_calendar_access_mode' => 'inherit',
                'tasks_access_mode' => 'inherit',
                'rhid_access_mode' => 'inherit',
                'denuncias_access_mode' => 'inherit',
            ])
            ->assertSessionHasErrors('collective_bargaining_month');
    }
}
