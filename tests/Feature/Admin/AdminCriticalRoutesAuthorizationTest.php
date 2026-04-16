<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminCriticalRoutesAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyAdminUser(): User
    {
        $company = Company::query()->create([
            'name' => 'Empresa cliente',
            'cnpj' => '55.555.555/0001-55',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);

        return User::factory()->companyAdmin($company->id)->create();
    }

    public function test_guest_is_redirected_from_admin_companies(): void
    {
        $this->get('/admin/companies')->assertRedirect(route('login'));
    }

    public function test_company_admin_cannot_access_admin_companies_index(): void
    {
        $this->actingAs($this->makeCompanyAdminUser())
            ->get('/admin/companies')
            ->assertForbidden();
    }

    public function test_super_admin_can_access_admin_companies_index(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/admin/companies')
            ->assertOk();
    }

    public function test_company_admin_cannot_access_admin_plans_index(): void
    {
        $this->actingAs($this->makeCompanyAdminUser())
            ->get('/admin/plans')
            ->assertForbidden();
    }

    public function test_super_admin_can_access_admin_plans_index(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/admin/plans')
            ->assertOk();
    }

    public function test_company_admin_cannot_access_admin_survey_templates_index(): void
    {
        $this->actingAs($this->makeCompanyAdminUser())
            ->get('/admin/survey-templates')
            ->assertForbidden();
    }

    public function test_super_admin_can_access_admin_survey_templates_index(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/admin/survey-templates')
            ->assertOk();
    }

    public function test_company_admin_cannot_access_admin_dashboard(): void
    {
        $this->actingAs($this->makeCompanyAdminUser())
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_company_admin_cannot_access_admin_landing_interest_index(): void
    {
        $this->actingAs($this->makeCompanyAdminUser())
            ->get('/admin/interessados-landing')
            ->assertForbidden();
    }

    public function test_super_admin_can_access_admin_landing_interest_index(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/admin/interessados-landing')
            ->assertOk();
    }
}
