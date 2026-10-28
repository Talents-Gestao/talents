<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\WorkspaceType;
use App\Models\Company;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Support\CompanyPortal;
use App\Support\WorkspaceManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompanyPortalLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_or_disabled_portal_returns_not_found(): void
    {
        $company = $this->company(['portal_slug' => 'passeg', 'portal_enabled' => false]);

        $this->get('/entrar/passeg')->assertNotFound();
        $this->get('/entrar/inexistente')->assertNotFound();

        $user = User::factory()->companyAdmin($company->id)->create();

        $this->post('/entrar/passeg', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertNotFound();

        $this->assertGuest();
    }

    public function test_portal_login_page_exposes_brand(): void
    {
        $this->company([
            'portal_slug' => 'passeg',
            'portal_enabled' => true,
            'brand_name' => 'Passeg',
            'brand_primary_color' => '#0F766E',
        ]);

        $this->get('/entrar/passeg?session_expired=1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('sessionExpired', true)
                ->where('portal.slug', 'passeg')
                ->where('portal.name', 'Passeg')
                ->where('portal.primary_color', '#0F766E')
                ->where('portal.login_url', '/entrar/passeg'));
    }

    public function test_company_user_enters_through_portal_and_logout_returns_there(): void
    {
        $company = $this->company([
            'portal_slug' => 'passeg',
            'portal_enabled' => true,
            'brand_name' => 'Passeg',
        ]);
        $user = User::factory()->companyAdmin($company->id)->create();

        $this->post('/entrar/passeg', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('client.dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('passeg', session(CompanyPortal::SESSION_KEY));
        $this->assertSame(
            $user->companyWorkspace($company->id)?->id,
            session(WorkspaceManager::SESSION_KEY),
        );

        $this->post(route('logout'))
            ->assertRedirect(route('portal.login', ['slug' => 'passeg']));

        $this->assertGuest();
    }

    public function test_user_from_another_company_cannot_use_the_portal(): void
    {
        $portalCompany = $this->company([
            'name' => 'Passeg',
            'portal_slug' => 'passeg',
            'portal_enabled' => true,
        ]);
        $other = $this->company(['name' => 'Outra']);
        $user = User::factory()->companyAdmin($other->id)->create();

        $this->post('/entrar/passeg', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertNotNull($portalCompany->id);
    }

    public function test_portal_login_selects_company_workspace_when_user_also_has_talents(): void
    {
        $company = $this->company([
            'portal_slug' => 'passeg',
            'portal_enabled' => true,
        ]);
        $user = User::factory()->superAdmin()->create();

        $companyWorkspace = UserWorkspace::create([
            'user_id' => $user->id,
            'workspace_type' => WorkspaceType::Company,
            'company_id' => $company->id,
            'role' => UserRole::CompanyAdmin,
            'is_owner' => false,
            'is_active' => true,
        ]);

        $this->post('/entrar/passeg', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('client.dashboard', absolute: false));

        $this->assertSame($companyWorkspace->id, session(WorkspaceManager::SESSION_KEY));
        $this->assertSame('passeg', session(CompanyPortal::SESSION_KEY));
    }

    public function test_super_admin_can_save_portal_settings(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = $this->company(['name' => 'Passeg']);

        $this->actingAs($admin)
            ->put(route('admin.companies.update', $company), [
                'name' => 'Passeg',
                'is_active' => true,
                'strategic_calendar_access_mode' => 'inherit',
                'tasks_access_mode' => 'inherit',
                'rhid_access_mode' => 'inherit',
                'denuncias_access_mode' => 'inherit',
                'ferias_access_mode' => 'inherit',
                'desligamento_access_mode' => 'inherit',
                'acompanhamento_access_mode' => 'inherit',
                'portal_enabled' => true,
                'portal_slug' => 'Passeg',
                'brand_name' => 'Passeg Pessoas',
                'brand_primary_color' => '#112233',
            ])
            ->assertRedirect(route('admin.companies.show', $company));

        $company->refresh();
        $this->assertTrue($company->portal_enabled);
        $this->assertSame('passeg', $company->portal_slug);
        $this->assertSame('Passeg Pessoas', $company->brand_name);
        $this->assertSame('#112233', $company->brand_primary_color);
    }

    public function test_enabling_portal_requires_slug(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = $this->company();

        $this->actingAs($admin)
            ->from(route('admin.companies.edit', $company))
            ->put(route('admin.companies.update', $company), [
                'name' => $company->name,
                'is_active' => true,
                'strategic_calendar_access_mode' => 'inherit',
                'tasks_access_mode' => 'inherit',
                'rhid_access_mode' => 'inherit',
                'denuncias_access_mode' => 'inherit',
                'ferias_access_mode' => 'inherit',
                'desligamento_access_mode' => 'inherit',
                'acompanhamento_access_mode' => 'inherit',
                'portal_enabled' => true,
                'portal_slug' => '',
            ])
            ->assertRedirect(route('admin.companies.edit', $company))
            ->assertSessionHasErrors('portal_slug');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function company(array $overrides = []): Company
    {
        return Company::query()->create(array_merge([
            'name' => 'Empresa Portal',
            'is_active' => true,
            'portal_enabled' => false,
        ], $overrides));
    }
}
