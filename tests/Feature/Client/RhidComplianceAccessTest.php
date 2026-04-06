<?php

namespace Tests\Feature\Client;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RhidComplianceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_open_rhid_compliance(): void
    {
        $company = Company::query()->create(['name' => 'Empresa Teste']);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->withoutVite();

        $this->actingAs($admin)
            ->get(route('client.rhid.compliance.index'))
            ->assertOk();
    }

    public function test_company_user_cannot_open_rhid_compliance(): void
    {
        $company = Company::query()->create(['name' => 'Empresa Teste']);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::CompanyUser,
        ]);

        $this->actingAs($user)
            ->get(route('client.rhid.compliance.index'))
            ->assertForbidden();
    }
}
