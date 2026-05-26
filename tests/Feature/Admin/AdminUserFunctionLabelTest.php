<?php

namespace Tests\Feature\Admin;

use App\Actions\SyncAdminUserPermissions;
use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminUserFunctionLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_index_marks_full_permissions_as_administrator_and_partial_as_equipe(): void
    {
        $this->withoutVite();

        $owner = User::factory()->superAdmin()->create(['is_owner' => true]);
        $fullAdmin = User::factory()->superAdmin()->create(['email' => 'full@talents.test']);
        $teamMember = User::factory()->superAdmin()->create(['email' => 'team@talents.test']);

        $allPermissions = [];
        foreach (AdminPermissionModule::all() as $module) {
            foreach (PermissionAction::all() as $action) {
                $allPermissions[] = [
                    'module' => $module->value,
                    'action' => $action->value,
                ];
            }
        }

        app(SyncAdminUserPermissions::class)->execute($fullAdmin->talentsWorkspace(), $allPermissions);
        app(SyncAdminUserPermissions::class)->execute($teamMember->talentsWorkspace(), [
            ['module' => AdminPermissionModule::Tarefas->value, 'action' => PermissionAction::View->value],
        ]);

        $this->actingAs($owner)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Index')
                ->has('users', 3)
                ->where('users', fn ($users) => collect($users)->contains(
                    fn ($u) => $u['email'] === $owner->email
                        && $u['is_owner'] === true
                        && $u['has_all_admin_permissions'] === true,
                ) && collect($users)->contains(
                    fn ($u) => $u['email'] === $fullAdmin->email
                        && $u['is_owner'] === false
                        && $u['has_all_admin_permissions'] === true,
                ) && collect($users)->contains(
                    fn ($u) => $u['email'] === $teamMember->email
                        && $u['is_owner'] === false
                        && $u['has_all_admin_permissions'] === false,
                ),
            ));
    }

    public function test_has_all_admin_permissions_requires_every_module_and_action(): void
    {
        $user = User::factory()->superAdmin()->create();

        $workspace = $user->talentsWorkspace();
        app(SyncAdminUserPermissions::class)->execute($workspace, [
            ['module' => AdminPermissionModule::Dashboard->value, 'action' => PermissionAction::View->value],
        ]);

        $user->setActiveWorkspace($workspace->fresh(['adminPermissions']));
        $this->assertFalse($user->hasAllAdminPermissions());
    }
}
