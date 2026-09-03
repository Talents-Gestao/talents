<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\Company;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserPermission;
use App\Support\AdminHomeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccessHierarchyVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_has_master_access_while_non_owner_needs_grants(): void
    {
        $owner = User::factory()->superAdmin()->create(['is_owner' => true]);
        $admin = User::factory()->superAdmin()->create([
            'is_owner' => false,
            'email' => 'gerente@talents.test',
        ]);

        foreach (AdminPermissionModule::all() as $module) {
            foreach (PermissionAction::all() as $action) {
                $this->assertTrue($owner->canAccessAdmin($module, $action));
                $this->assertFalse($admin->canAccessAdmin($module, $action));
            }
        }

        $this->assertSame(['*' => true], $owner->adminPermissionMatrixForFrontend());
        $this->assertSame([], $admin->adminPermissionMatrixForFrontend());
        $this->assertTrue($owner->hasAllAdminPermissions());
        $this->assertFalse($admin->hasAllAdminPermissions());

        $resolver = app(AdminHomeResolver::class);
        $this->assertSame('admin.dashboard', $resolver->routeNameFor($owner));
        $this->assertSame('admin.dashboard', $resolver->routeNameFor($admin));
    }

    public function test_hidden_capacitacao_is_not_assignable_in_permission_matrices(): void
    {
        $this->assertNotContains(PermissionModule::Capacitacao, PermissionModule::all());
        $this->assertNotContains(AdminPermissionModule::Training, AdminPermissionModule::all());
    }

    public function test_company_roles_cannot_access_admin_modules(): void
    {
        $company = $this->createCompanyWithModules([
            PermissionModule::Feedbacks,
            PermissionModule::Ferias,
            PermissionModule::Acompanhamento,
        ]);

        $companyAdmin = User::factory()->companyAdmin($company->id)->create();
        $companyUser = User::factory()->companyUser($company->id)->create();

        $this->assertFalse($companyAdmin->canAccessAdmin(AdminPermissionModule::Dashboard, PermissionAction::View));
        $this->assertFalse($companyUser->canAccessAdmin(AdminPermissionModule::Dashboard, PermissionAction::View));
        $this->assertFalse($companyAdmin->hasAllAdminPermissions());
        $this->assertNull(app(AdminHomeResolver::class)->routeNameFor($companyAdmin));
    }

    public function test_company_admin_sees_enabled_company_modules_including_ferias(): void
    {
        $company = $this->createCompanyWithModules([
            PermissionModule::Feedbacks,
            PermissionModule::Ferias,
            PermissionModule::Acompanhamento,
            PermissionModule::Pesquisas,
        ]);

        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->assertTrue($admin->canAccess(PermissionModule::Feedbacks, PermissionAction::View));
        $this->assertTrue($admin->canAccess(PermissionModule::Ferias, PermissionAction::View));
        $this->assertTrue($admin->canAccess(PermissionModule::Acompanhamento, PermissionAction::Create));
        $this->assertTrue($admin->canAccess(PermissionModule::Pesquisas, PermissionAction::Edit));
    }

    public function test_company_user_has_limited_company_visibility(): void
    {
        $company = $this->createCompanyWithModules([
            PermissionModule::Feedbacks,
            PermissionModule::Ferias,
            PermissionModule::Acompanhamento,
            PermissionModule::Pesquisas,
        ]);

        $user = User::factory()->companyUser($company->id)->create();

        $this->assertTrue($user->canAccess(PermissionModule::Feedbacks, PermissionAction::View));
        $this->assertFalse($user->canAccess(PermissionModule::Feedbacks, PermissionAction::Delete));

        $this->assertTrue($user->canAccess(PermissionModule::Acompanhamento, PermissionAction::View));
        $this->assertTrue($user->canAccess(PermissionModule::Acompanhamento, PermissionAction::Edit));
        $this->assertFalse($user->canAccess(PermissionModule::Acompanhamento, PermissionAction::Create));

        $this->assertFalse($user->canAccess(PermissionModule::Ferias, PermissionAction::View));
        $this->assertFalse($user->canAccess(PermissionModule::Pesquisas, PermissionAction::View));

        $workspace = $user->workspaces()->firstOrFail();
        $user->setActiveWorkspace($workspace);

        UserPermission::query()->create([
            'user_workspace_id' => $workspace->id,
            'module' => PermissionModule::Pesquisas->value,
            'action' => PermissionAction::View->value,
        ]);

        $this->assertTrue($user->canAccess(PermissionModule::Pesquisas, PermissionAction::View));
        $this->assertFalse($user->canAccess(PermissionModule::Pesquisas, PermissionAction::Edit));
    }

    public function test_inactive_super_admin_loses_all_admin_visibility(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'is_owner' => false,
            'is_active' => false,
        ]);

        $this->assertFalse($admin->canAccessAdmin(AdminPermissionModule::FinanceiroVendas, PermissionAction::View));
        $this->assertFalse($admin->hasAllAdminPermissions());
        $this->assertSame([], $admin->adminPermissionMatrixForFrontend());
        $this->assertNull(app(AdminHomeResolver::class)->routeNameFor($admin));
    }

    /**
     * @param  list<PermissionModule>  $modules
     */
    private function createCompanyWithModules(array $modules): Company
    {
        $plan = Plan::query()->create([
            'name' => 'Plano hierarquia',
            'slug' => 'hierarquia-'.Str::random(8),
            'price_monthly_cents' => 0,
            'is_active' => true,
        ]);

        $moduleIds = [];
        foreach ($modules as $module) {
            $key = match ($module) {
                PermissionModule::Pesquisas => Module::KEY_NR1,
                PermissionModule::Feedbacks => Module::KEY_FEEDBACKS,
                PermissionModule::Ferias => Module::KEY_FERIAS,
                PermissionModule::Acompanhamento => Module::KEY_ACOMPANHAMENTO,
                default => $module->value,
            };

            $record = Module::query()->firstOrCreate(
                ['key' => $key],
                ['name' => $module->label(), 'description' => 'Teste'],
            );
            $moduleIds[] = $record->id;
        }

        $plan->modules()->sync(array_values(array_unique($moduleIds)));

        $company = Company::query()->create([
            'name' => 'Empresa Hierarquia',
            'cnpj' => '12.345.678/0001-'.random_int(10, 99),
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);

        Subscription::query()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addYear(),
            'status' => 'active',
        ]);

        return $company->fresh();
    }
}
