<?php

namespace Tests\Feature\Permissions;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

class UserPermissionsTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    public function test_company_user_with_survey_view_can_open_surveys_index(): void
    {
        $this->withoutVite();

        $fx = $this->createSurveyFixture();
        $user = User::factory()->create([
            'company_id' => $fx->company->id,
            'role' => UserRole::CompanyUser,
        ]);
        $workspace = $user->workspaces()->first();
        UserPermission::query()->create([
            'user_workspace_id' => $workspace->id,
            'module' => PermissionModule::Pesquisas->value,
            'action' => PermissionAction::View->value,
        ]);

        $this->actingAs($user)
            ->get(route('client.surveys.index'))
            ->assertOk();
    }

    public function test_company_user_without_permission_cannot_open_surveys_index(): void
    {
        $this->withoutVite();

        $fx = $this->createSurveyFixture();
        $user = User::factory()->create([
            'company_id' => $fx->company->id,
            'role' => UserRole::CompanyUser,
        ]);

        $this->actingAs($user)
            ->get(route('client.surveys.index'))
            ->assertForbidden();
    }

    public function test_company_user_with_view_but_not_edit_cannot_run_ai_analysis(): void
    {
        $fx = $this->createSurveyFixture();
        $user = User::factory()->create([
            'company_id' => $fx->company->id,
            'role' => UserRole::CompanyUser,
        ]);
        $workspace = $user->workspaces()->first();
        UserPermission::query()->create([
            'user_workspace_id' => $workspace->id,
            'module' => PermissionModule::Pesquisas->value,
            'action' => PermissionAction::View->value,
        ]);

        $this->actingAs($user)
            ->post(route('client.surveys.ai-analysis', $fx->survey))
            ->assertForbidden();
    }

    public function test_company_admin_can_create_user_via_client_area(): void
    {
        $this->withoutVite();

        $company = Company::query()->create([
            'name' => 'Empresa U',
            'cnpj' => '66.666.666/0001-66',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);
        $this->subscribeCompanyToNr1($company);

        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($admin)
            ->post(route('client.usuarios.store'), [
                'name' => 'Colaborador',
                'email' => 'colab@test.local',
                'is_active' => true,
                'permissions' => [
                    ['module' => PermissionModule::Denuncias->value, 'action' => PermissionAction::View->value],
                ],
            ])
            ->assertRedirect(route('client.usuarios.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'colab@test.local',
            'role' => UserRole::CompanyUser->value,
            'company_id' => $company->id,
        ]);
    }

    public function test_company_admin_cannot_open_complaints_without_denuncias_module(): void
    {
        $this->withoutVite();

        $company = Company::query()->create([
            'name' => 'Empresa Sem Denuncias',
            'cnpj' => '77.777.777/0001-77',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);
        $this->subscribeCompanyToNr1($company, withRhid: false, withDenuncias: false);

        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($admin)
            ->get(route('client.complaints.index'))
            ->assertForbidden();
    }

    public function test_company_admin_can_open_complaints_with_denuncias_module_on_plan(): void
    {
        $this->withoutVite();

        $company = Company::query()->create([
            'name' => 'Empresa Com Denuncias',
            'cnpj' => '88.888.888/0001-88',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);
        $this->subscribeCompanyToNr1($company, withRhid: false, withDenuncias: true);

        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($admin)
            ->get(route('client.complaints.index'))
            ->assertOk();
    }

    public function test_denuncias_override_enables_complaints_without_plan_module(): void
    {
        $this->withoutVite();

        $company = Company::query()->create([
            'name' => 'Empresa Override Denuncias',
            'cnpj' => '99.999.999/0001-99',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
            'denuncias_access' => true,
        ]);
        $this->subscribeCompanyToNr1($company, withRhid: false, withDenuncias: false);

        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($admin)
            ->get(route('client.complaints.index'))
            ->assertOk();
    }

    public function test_denuncias_override_disables_complaints_even_with_plan_module(): void
    {
        $this->withoutVite();

        $company = Company::query()->create([
            'name' => 'Empresa Denuncias Bloqueadas',
            'cnpj' => '11.111.111/0001-11',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
            'denuncias_access' => false,
        ]);
        $this->subscribeCompanyToNr1($company, withRhid: false, withDenuncias: true);

        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($admin)
            ->get(route('client.complaints.index'))
            ->assertForbidden();
    }

    public function test_has_module_enabled_denuncias_requires_denuncias_plan_key(): void
    {
        $nr1 = Module::query()->firstOrCreate(
            ['key' => Module::KEY_NR1],
            ['name' => 'NR1', 'description' => 'Teste']
        );
        $plan = Plan::query()->create([
            'name' => 'Somente NR1',
            'slug' => 'nr1-only-'.Str::random(8),
            'price_monthly_cents' => 0,
            'is_active' => true,
        ]);
        $plan->modules()->sync([$nr1->id]);

        $company = Company::query()->create([
            'name' => 'Empresa NR1 Puro',
            'cnpj' => '22.222.222/0001-22',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);
        Subscription::query()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
            'status' => 'active',
        ]);

        $this->assertFalse($company->hasModuleEnabled(PermissionModule::Denuncias));
        $this->assertTrue($company->hasModuleEnabled(PermissionModule::Pesquisas));
        $this->assertFalse($company->hasModuleEnabled(PermissionModule::Capacitacao));
    }

    public function test_company_user_form_does_not_include_hidden_capacitacao_module(): void
    {
        $this->withoutVite();

        $company = Company::query()->create([
            'name' => 'Empresa Sem Capacitação',
            'cnpj' => '33.333.333/0001-33',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);
        $this->subscribeCompanyToNr1($company);

        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($admin)
            ->get(route('client.usuarios.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/Users/Form')
                ->where(
                    'permissionModules',
                    fn ($modules) => collect($modules)->pluck('value')->doesntContain('capacitacao')
                        && collect($modules)->pluck('label')->doesntContain('Capacitação'),
                )
            );
    }

    public function test_company_admin_cannot_open_hidden_capacitacao_screen(): void
    {
        $this->withoutVite();

        $company = Company::query()->create([
            'name' => 'Empresa Tela Oculta',
            'cnpj' => '44.444.444/0001-44',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);
        $this->subscribeCompanyToNr1($company);

        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($admin)
            ->get(route('client.training.index'))
            ->assertForbidden();
    }
}
