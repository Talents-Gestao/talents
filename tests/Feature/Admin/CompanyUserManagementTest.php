<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompanyUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_company_users_index(): void
    {
        $this->withoutVite();

        $company = Company::query()->create([
            'name' => 'Empresa Admin',
            'cnpj' => '77.777.777/0001-77',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);

        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)
            ->get(route('admin.companies.users.index', $company))
            ->assertOk();
    }

    public function test_company_admin_cannot_view_admin_company_users_index(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa C',
            'cnpj' => '88.888.888/0001-88',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);
        $this->subscribeCompanyToNr1($company);

        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($admin)
            ->get(route('admin.companies.users.index', $company))
            ->assertForbidden();
    }
}
