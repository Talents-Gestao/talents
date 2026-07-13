<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\LandingInterestSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminDashboardLeadsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_dashboard_lists_recent_leads_even_after_notification_mail_was_sent(): void
    {
        Mail::fake();

        $this->from('/')->post(route('landing.interest'), [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'phone' => '(11) 98888-7777',
            'company' => 'ACME',
            'message' => 'Gostaria de uma demo.',
        ])->assertRedirect();

        $submission = LandingInterestSubmission::query()->where('email', 'joao@example.com')->firstOrFail();
        $this->assertNotNull($submission->mail_sent_at);

        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Dashboard')
                ->has('recentLeads', 1)
                ->where('recentLeads.0.id', $submission->id)
                ->where('recentLeads.0.email', 'joao@example.com'));
    }
}
