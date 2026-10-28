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

    public function test_dashboard_entry_allows_talents_home_without_explicit_dashboard_grant(): void
    {
        $user = User::factory()->superAdmin()->create(['is_owner' => false]);
        $talentsWorkspace = $user->talentsWorkspace();
        $this->assertNotNull($talentsWorkspace);

        $this->actingAs($user)
            ->withSession([WorkspaceManager::SESSION_KEY => $talentsWorkspace->id])
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.dashboard', absolute: false));

        $this->actingAs($user)
            ->withSession([WorkspaceManager::SESSION_KEY => $talentsWorkspace->id])
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_workspace_switch_updates_legacy_columns_and_session(): void
    {
        $user = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = Company::query()->create([
            'name' => 'Pasqualino Teste',
            'cnpj' => '98.765.432/0001-10',
            'is_active' => true,
        ]);

        $companyWorkspace = UserWorkspace::create([
            'user_id' => $user->id,
            'workspace_type' => WorkspaceType::Company,
            'company_id' => $company->id,
            'role' => UserRole::CompanyAdmin,
            'is_owner' => false,
            'is_active' => true,
        ]);

        $talentsWorkspace = $user->talentsWorkspace();
        $this->assertNotNull($talentsWorkspace);

        $this->actingAs($user)
            ->withSession([WorkspaceManager::SESSION_KEY => $talentsWorkspace->id])
            ->post(route('workspaces.select.store'), [
                'workspace_id' => $companyWorkspace->id,
            ])
            ->assertRedirect(route('client.dashboard', absolute: false));

        $this->assertEquals($companyWorkspace->id, session(WorkspaceManager::SESSION_KEY));
        $user->refresh();
        $this->assertSame(UserRole::CompanyAdmin, $user->role);
        $this->assertSame($company->id, (int) $user->getRawOriginal('company_id'));

        $this->actingAs($user)
            ->post(route('workspaces.select.store'), [
                'workspace_id' => $talentsWorkspace->id,
            ])
            ->assertRedirect(route('admin.dashboard', absolute: false));

        $user->refresh();
        $this->assertSame(UserRole::SuperAdmin, $user->role);
        $this->assertNull($user->getRawOriginal('company_id'));
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
