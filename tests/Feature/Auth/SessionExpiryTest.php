<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_receives_session_expired_flag_from_query(): void
    {
        $response = $this->get('/login?session_expired=1');

        $response
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Login')
                ->where('sessionExpired', true));
    }

    public function test_authenticated_inertia_pages_share_session_expiry_metadata(): void
    {
        $user = User::factory()->superAdmin()->create();

        $lifetimeMinutes = (int) config('session.lifetime');
        $warningMinutes = (int) config('session.warning_minutes', 5);

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('session.expires_at')
                ->where('session.lifetime_minutes', $lifetimeMinutes)
                ->where('session.warning_minutes', $warningMinutes));
    }
}
