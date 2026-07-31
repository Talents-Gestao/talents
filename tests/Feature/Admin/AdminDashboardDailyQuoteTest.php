<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminDashboardDailyQuoteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_dashboard_includes_daily_quote(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Dashboard')
                ->has('dailyQuote')
                ->where('dailyQuote.deck_title', 'Baralho da Metamorfose 2026')
                ->where('dailyQuote.word', fn ($word) => is_string($word) && $word !== ''));
    }

    public function test_admin_home_includes_daily_quote_for_non_owner_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => false]);

        $this->actingAs($admin->fresh())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('dailyQuote')
                ->where('dailyQuote.deck_title', 'Baralho da Metamorfose 2026'));
    }
}
