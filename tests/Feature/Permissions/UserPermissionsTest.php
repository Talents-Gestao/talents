<?php

namespace Tests\Feature\Permissions;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
        UserPermission::query()->create([
            'user_id' => $user->id,
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
        UserPermission::query()->create([
            'user_id' => $user->id,
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
}
