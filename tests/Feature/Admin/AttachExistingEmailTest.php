<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\WorkspaceType;
use App\Models\Company;
use App\Models\User;
use App\Models\UserWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttachExistingEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_user_creation_reuses_existing_talents_email(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create();
        $company = Company::query()->create([
            'name' => 'Empresa Teste',
            'cnpj' => '98.765.432/0001-10',
            'is_active' => true,
        ]);

        $talentsUser = User::factory()->superAdmin()->create([
            'email' => 'dual@example.com',
        ]);

        $this->assertEquals(1, User::query()->where('email', 'dual@example.com')->count());

        $this->actingAs($admin)
            ->post(route('admin.companies.users.store', $company), [
                'name' => 'Colaborador Dual',
                'email' => 'dual@example.com',
                'role' => UserRole::CompanyUser->value,
                'is_active' => true,
                'permissions' => [],
            ])
            ->assertRedirect(route('admin.companies.users.index', $company));

        $this->assertEquals(1, User::query()->where('email', 'dual@example.com')->count());
        $this->assertTrue(
            UserWorkspace::query()
                ->where('user_id', $talentsUser->id)
                ->where('workspace_type', WorkspaceType::Company)
                ->where('company_id', $company->id)
                ->exists()
        );
    }

    public function test_talents_user_creation_reuses_existing_company_email(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create();
        $company = Company::query()->create([
            'name' => 'Empresa Teste',
            'cnpj' => '98.765.432/0001-10',
            'is_active' => true,
        ]);

        $companyUser = User::factory()->companyUser($company->id)->create([
            'email' => 'dual2@example.com',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Colaborador Dual',
                'email' => 'dual2@example.com',
                'is_active' => true,
                'is_commercial' => false,
                'permissions' => [
                    ['module' => 'dashboard', 'action' => 'view'],
                ],
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertEquals(1, User::query()->where('email', 'dual2@example.com')->count());
        $this->assertTrue(
            UserWorkspace::query()
                ->where('user_id', $companyUser->id)
                ->where('workspace_type', WorkspaceType::Talents)
                ->exists()
        );
    }
}
