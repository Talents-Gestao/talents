<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Actions\SyncAdminUserPermissions;
use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminUserPermissionsMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_collaborator_permissions_and_block_financeiro(): void
    {
        $this->withoutVite();

        $owner = User::factory()->superAdmin()->create(['is_owner' => true]);
        $collaborator = User::factory()->superAdmin()->create([
            'is_owner' => false,
            'name' => 'Colaborador Teste',
            'email' => 'colab-perms@example.com',
        ]);

        $workspace = $collaborator->talentsWorkspace();
        $this->assertNotNull($workspace);
        app(SyncAdminUserPermissions::class)->execute(
            $workspace,
            SyncAdminUserPermissions::allGrants(),
        );

        $this->actingAs($owner)
            ->put(route('admin.users.update', $collaborator), [
                'name' => $collaborator->name,
                'email' => $collaborator->email,
                'is_active' => true,
                'is_commercial' => false,
                'permissions' => [
                    [
                        'module' => AdminPermissionModule::Dashboard->value,
                        'action' => PermissionAction::View->value,
                    ],
                    [
                        'module' => AdminPermissionModule::Equipe->value,
                        'action' => PermissionAction::View->value,
                    ],
                ],
            ])
            ->assertRedirect(route('admin.users.index'));

        $collaborator->refresh();
        $collaborator->setActiveWorkspace($collaborator->talentsWorkspace()?->fresh(['adminPermissions']));

        $this->assertTrue($collaborator->canAccessAdmin(AdminPermissionModule::Dashboard, PermissionAction::View));
        $this->assertFalse($collaborator->canAccessAdmin(AdminPermissionModule::FinanceiroVendas, PermissionAction::View));

        $this->actingAs($collaborator)
            ->get(route('admin.financeiro.vendas.index'))
            ->assertForbidden();
    }

    public function test_edit_form_includes_permission_modules(): void
    {
        $this->withoutVite();

        $owner = User::factory()->superAdmin()->create(['is_owner' => true]);
        $collaborator = User::factory()->superAdmin()->create(['is_owner' => false]);

        $this->actingAs($owner)
            ->get(route('admin.users.edit', $collaborator))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Form')
                ->has('permissionModules')
                ->has('permissionActions')
                ->where('user.is_owner', false)
                ->where(
                    'permissionModules',
                    fn ($modules) => collect($modules)->pluck('value')->doesntContain('training')
                        && collect($modules)->pluck('label')->doesntContain('Capacitação'),
                )
            );
    }
}
