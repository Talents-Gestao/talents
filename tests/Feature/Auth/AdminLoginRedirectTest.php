<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_login_redirects_to_dashboard(): void
    {
        $user = User::factory()->superAdmin()->create(['is_owner' => false]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_dashboard_route_stays_on_admin_dashboard_for_any_super_admin(): void
    {
        $user = User::factory()->superAdmin()->create(['is_owner' => false]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('admin.dashboard'));
    }
}
