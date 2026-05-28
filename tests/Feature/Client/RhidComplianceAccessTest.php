<?php

namespace Tests\Feature\Client;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RhidComplianceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_open_rhid_compliance(): void
    {
        $company = Company::query()->create(['name' => 'Empresa Teste']);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->withoutVite();

        $this->actingAs($admin)
            ->get(route('client.rhid.compliance.index'))
            ->assertOk();
    }

    public function test_company_admin_can_open_rhid_collaborator_page(): void
    {
        $company = Company::query()->create(['name' => 'Empresa Teste']);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->withoutVite();

        $this->actingAs($admin)
            ->get(route('client.rhid.collaborators.show', ['person' => 1]))
            ->assertOk();
    }

    public function test_company_user_cannot_open_rhid_compliance(): void
    {
        $company = Company::query()->create(['name' => 'Empresa Teste']);
        $this->subscribeCompanyToNr1($company);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::CompanyUser,
        ]);

        $this->actingAs($user)
            ->get(route('client.rhid.compliance.index'))
            ->assertForbidden();
    }

    public function test_company_user_cannot_open_rhid_collaborator_page(): void
    {
        $company = Company::query()->create(['name' => 'Empresa Teste 2']);
        $this->subscribeCompanyToNr1($company);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::CompanyUser,
        ]);

        $this->actingAs($user)
            ->get(route('client.rhid.collaborators.show', ['person' => 1]))
            ->assertForbidden();
    }

    public function test_company_user_with_rhid_view_permission_can_open_compliance(): void
    {
        $this->withoutVite();

        $company = Company::query()->create(['name' => 'Empresa RHID Perm']);
        $this->subscribeCompanyToNr1($company);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::CompanyUser,
        ]);
        $workspace = $user->workspaces()->first();
        UserPermission::query()->create([
            'user_workspace_id' => $workspace->id,
            'module' => PermissionModule::Rhid->value,
            'action' => PermissionAction::View->value,
        ]);

        $this->actingAs($user)
            ->get(route('client.rhid.compliance.index'))
            ->assertOk();
    }

    public function test_company_admin_cannot_open_rhid_compliance_without_rhid_module(): void
    {
        $company = Company::query()->create(['name' => 'Empresa sem RHID']);
        $this->subscribeCompanyToNr1($company, withRhid: false);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($admin)
            ->get(route('client.rhid.compliance.index'))
            ->assertForbidden();
    }
}
