<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\WorkspaceType;
use App\Models\Company;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Support\WorkspaceManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiWorkspaceLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_single_workspace_redirects_directly(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('admin.dashboard', absolute: false));
        $this->assertNotNull(session(WorkspaceManager::SESSION_KEY));
    }

    public function test_user_with_multiple_workspaces_is_sent_to_selection(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Teste',
            'cnpj' => '12.345.678/0001-90',
            'is_active' => true,
        ]);
        $user = User::factory()->superAdmin()->create();

        UserWorkspace::create([
            'user_id' => $user->id,
            'workspace_type' => WorkspaceType::Company,
            'company_id' => $company->id,
            'role' => UserRole::CompanyUser,
            'is_owner' => false,
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('workspaces.select'));
        $this->assertNull(session(WorkspaceManager::SESSION_KEY));
    }

    public function test_workspace_selection_redirects_to_admin(): void
    {
        $user = User::factory()->superAdmin()->create();
        $company = Company::query()->create([
            'name' => 'Empresa Teste',
            'cnpj' => '12.345.678/0001-90',
            'is_active' => true,
        ]);

        $companyWorkspace = UserWorkspace::create([
            'user_id' => $user->id,
            'workspace_type' => WorkspaceType::Company,
            'company_id' => $company->id,
            'role' => UserRole::CompanyUser,
            'is_owner' => false,
            'is_active' => true,
        ]);

        $talentsWorkspace = $user->talentsWorkspace();

        $this->actingAs($user)
            ->post(route('workspaces.select.store'), [
                'workspace_id' => $talentsWorkspace->id,
            ])
            ->assertRedirect(route('admin.dashboard', absolute: false));

        $this->assertEquals($talentsWorkspace->id, session(WorkspaceManager::SESSION_KEY));

        $this->actingAs($user)
            ->post(route('workspaces.select.store'), [
                'workspace_id' => $companyWorkspace->id,
            ])
            ->assertRedirect(route('client.dashboard', absolute: false));

        $this->assertEquals($companyWorkspace->id, session(WorkspaceManager::SESSION_KEY));
    }

    public function test_cannot_select_foreign_workspace(): void
    {
        $user = User::factory()->superAdmin()->create();
        $other = User::factory()->superAdmin()->create();

        $foreignWorkspace = $other->talentsWorkspace();

        $this->actingAs($user)
            ->post(route('workspaces.select.store'), [
                'workspace_id' => $foreignWorkspace->id,
            ])
            ->assertSessionHasErrors('workspace_id');
    }
}
