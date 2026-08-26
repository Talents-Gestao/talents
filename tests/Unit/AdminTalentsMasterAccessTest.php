<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Actions\SyncAdminUserPermissions;
use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Models\User;
use App\Support\AdminHomeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTalentsMasterAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_owner_super_admin_without_grants_cannot_access_financeiro(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => false]);

        $this->assertFalse($admin->canAccessAdmin(AdminPermissionModule::FinanceiroVendas, PermissionAction::View));
        $this->assertFalse($admin->hasAllAdminPermissions());
        $this->assertSame([], $admin->adminPermissionMatrixForFrontend());
        $this->assertSame('admin.dashboard', app(AdminHomeResolver::class)->routeNameFor($admin));
    }

    public function test_non_owner_with_financeiro_vendas_grant_can_access(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => false]);
        $workspace = $admin->talentsWorkspace();
        $this->assertNotNull($workspace);

        app(SyncAdminUserPermissions::class)->execute($workspace, [
            ['module' => AdminPermissionModule::FinanceiroVendas->value, 'action' => PermissionAction::View->value],
            ['module' => AdminPermissionModule::Dashboard->value, 'action' => PermissionAction::View->value],
        ]);

        $admin->unsetRelation('workspaces');
        $admin->setActiveWorkspace($workspace->fresh(['adminPermissions']));

        $this->assertTrue($admin->canAccessAdmin(AdminPermissionModule::FinanceiroVendas, PermissionAction::View));
        $this->assertFalse($admin->canAccessAdmin(AdminPermissionModule::FinanceiroVendas, PermissionAction::Create));
        $this->assertFalse($admin->canAccessAdmin(AdminPermissionModule::FinanceiroContasAPagar, PermissionAction::View));
        $this->assertFalse($admin->canAccessAdmin(AdminPermissionModule::ComercialPropostas, PermissionAction::View));
    }

    public function test_legacy_financeiro_grant_still_covers_submodules(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => false]);
        $workspace = $admin->talentsWorkspace();
        $this->assertNotNull($workspace);

        $workspace->adminPermissions()->create([
            'module' => AdminPermissionModule::Financeiro->value,
            'action' => PermissionAction::View->value,
        ]);

        $admin->unsetRelation('workspaces');
        $admin->setActiveWorkspace($workspace->fresh(['adminPermissions']));

        $this->assertTrue($admin->canAccessAdmin(AdminPermissionModule::FinanceiroVendas, PermissionAction::View));
        $this->assertTrue($admin->canAccessAdmin(AdminPermissionModule::FinanceiroContasAPagar, PermissionAction::View));
        $matrix = $admin->adminPermissionMatrixForFrontend();
        $this->assertContains(PermissionAction::View->value, $matrix[AdminPermissionModule::FinanceiroVendas->value] ?? []);
    }

    public function test_owner_super_admin_has_master_access(): void
    {
        $owner = User::factory()->superAdmin()->create(['is_owner' => true]);

        $this->assertTrue($owner->canAccessAdmin(AdminPermissionModule::EntrevistasIa, PermissionAction::View));
        $this->assertTrue($owner->canAccessAdmin(AdminPermissionModule::FinanceiroVendas, PermissionAction::View));
        $this->assertTrue($owner->hasAllAdminPermissions());
        $this->assertSame(['*' => true], $owner->adminPermissionMatrixForFrontend());
    }

    public function test_inactive_super_admin_has_no_admin_access(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'is_owner' => false,
            'is_active' => false,
        ]);

        $this->assertFalse($admin->canAccessAdmin(AdminPermissionModule::Dashboard, PermissionAction::View));
        $this->assertFalse($admin->hasAllAdminPermissions());
        $this->assertSame([], $admin->adminPermissionMatrixForFrontend());
    }

    public function test_full_grants_report_has_all_admin_permissions(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => false]);
        $workspace = $admin->talentsWorkspace();
        $this->assertNotNull($workspace);

        app(SyncAdminUserPermissions::class)->execute(
            $workspace,
            SyncAdminUserPermissions::allGrants(),
        );

        $admin->setActiveWorkspace($workspace->fresh(['adminPermissions']));

        $this->assertTrue($admin->hasAllAdminPermissions());
        $this->assertTrue($admin->canAccessAdmin(AdminPermissionModule::SolidesBancoTalentos, PermissionAction::Create));
    }
}
