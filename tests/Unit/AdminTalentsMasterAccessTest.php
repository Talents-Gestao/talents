<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Models\User;
use App\Support\AdminHomeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTalentsMasterAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_owner_super_admin_has_full_admin_access_without_grants(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => false]);

        $this->assertTrue($admin->canAccessAdmin(AdminPermissionModule::Entrevistas, PermissionAction::View));
        $this->assertTrue($admin->canAccessAdmin(AdminPermissionModule::Financeiro, PermissionAction::View));
        $this->assertTrue($admin->canAccessAdmin(AdminPermissionModule::Solides, PermissionAction::Create));
        $this->assertTrue($admin->hasAllAdminPermissions());
        $this->assertSame(['*' => true], $admin->adminPermissionMatrixForFrontend());
        $this->assertSame('admin.dashboard', app(AdminHomeResolver::class)->routeNameFor($admin));
    }

    public function test_owner_super_admin_has_same_master_access(): void
    {
        $owner = User::factory()->superAdmin()->create(['is_owner' => true]);

        $this->assertTrue($owner->canAccessAdmin(AdminPermissionModule::Entrevistas, PermissionAction::View));
        $this->assertTrue($owner->canAccessAdmin(AdminPermissionModule::Financeiro, PermissionAction::View));
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
}
