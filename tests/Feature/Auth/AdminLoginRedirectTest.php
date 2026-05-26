<?php

namespace Tests\Feature\Auth;

use App\Actions\SyncAdminUserPermissions;
use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_without_dashboard_redirects_to_first_allowed_module(): void
    {
        $user = User::factory()->superAdmin()->create();

        app(SyncAdminUserPermissions::class)->execute($user->talentsWorkspace(), [
            ['module' => AdminPermissionModule::Comercial->value, 'action' => PermissionAction::View->value],
            ['module' => AdminPermissionModule::Entrevistas->value, 'action' => PermissionAction::View->value],
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.comercial.dashboard', absolute: false));
    }

    public function test_dashboard_route_redirects_to_comercial_when_dashboard_not_allowed(): void
    {
        $user = User::factory()->superAdmin()->create();

        app(SyncAdminUserPermissions::class)->execute($user->talentsWorkspace(), [
            ['module' => AdminPermissionModule::Comercial->value, 'action' => PermissionAction::View->value],
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('admin.comercial.dashboard'));
    }
}
