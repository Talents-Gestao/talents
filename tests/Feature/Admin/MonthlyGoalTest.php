<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdminDashboardSettings;
use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use App\Models\User;
use App\Support\Admin\AdminHomeDashboardBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class MonthlyGoalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_builder_uses_config_fallback_when_no_setting_in_database(): void
    {
        config(['talents.dashboard.monthly_revenue_goal_cents' => 3_500_000]);

        $this->assertDatabaseCount('admin_dashboard_settings', 0);

        $home = app(AdminHomeDashboardBuilder::class)->build();

        $this->assertSame(3_500_000, $home['monthly_goal']['goal_cents']);
    }

    public function test_builder_uses_persisted_setting_when_present(): void
    {
        config(['talents.dashboard.monthly_revenue_goal_cents' => 2_000_000]);

        AdminDashboardSettings::query()->create([
            'monthly_revenue_goal_cents' => 5_000_000,
        ]);

        $home = app(AdminHomeDashboardBuilder::class)->build();

        $this->assertSame(5_000_000, $home['monthly_goal']['goal_cents']);
    }

    public function test_guest_cannot_update_monthly_goal(): void
    {
        $this->patch(route('admin.dashboard.monthly-goal.update'), [
            'goal_reais' => 25000,
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('admin_dashboard_settings', 0);
    }

    public function test_admin_can_update_monthly_goal_and_dashboard_reflects_it(): void
    {
        $admin = User::factory()->superAdmin()->create();

        config(['talents.dashboard.monthly_revenue_goal_cents' => 2_000_000]);

        CommercialProposal::query()->create([
            'code' => 'PROP-META-1',
            'client_name' => 'Cliente Meta',
            'is_closed' => true,
            'total_final_cents' => 1_000_000,
        ]);

        $proposalId = CommercialProposal::query()->where('code', 'PROP-META-1')->value('id');

        CommercialSale::query()->create([
            'code' => 'VENDA-META-1',
            'proposal_id' => $proposalId,
            'client_name' => 'Cliente Meta',
            'status' => CommercialSale::STATUS_ABERTA,
            'total_cents' => 1_000_000,
            'sold_at' => now(),
            'created_by' => $admin->id,
            'seller_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.dashboard.monthly-goal.update'), [
                'goal_reais' => 10000,
            ])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('admin_dashboard_settings', [
            'monthly_revenue_goal_cents' => 1_000_000,
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Dashboard')
                ->where('monthlyGoal.goal_cents', 1_000_000)
                ->where('monthlyGoal.current_cents', 1_000_000)
                ->where('monthlyGoal.percent', 100));
    }

    public function test_monthly_goal_validation_rejects_invalid_values(): void
    {
        $admin = User::factory()->superAdmin()->create();

        foreach ([null, '', 0, -10] as $invalid) {
            $payload = ['goal_reais' => $invalid];
            if ($invalid === null) {
                $payload = [];
            }

            $this->actingAs($admin)
                ->from(route('admin.dashboard'))
                ->patch(route('admin.dashboard.monthly-goal.update'), $payload)
                ->assertSessionHasErrors('goal_reais');
        }

        $this->assertDatabaseCount('admin_dashboard_settings', 0);
    }

    public function test_monthly_goal_current_excludes_sales_from_other_months(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->createSale($admin, 'PROP-META-CUR', 'VENDA-META-CUR', 800_000, now());
        $this->createSale($admin, 'PROP-META-OLD', 'VENDA-META-OLD', 9_000_000, now()->subMonth());
        $this->createSale($admin, 'PROP-META-FUT', 'VENDA-META-FUT', 7_000_000, now()->addMonth());

        $home = app(AdminHomeDashboardBuilder::class)->build();

        $this->assertSame(800_000, $home['monthly_goal']['current_cents']);
    }

    public function test_monthly_goal_recurring_sale_counts_only_monthly_parcel(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-META-REC',
            'client_name' => 'Cliente Recorrente',
            'is_closed' => true,
            'total_final_cents' => 1_200_000,
            'is_recurring' => true,
            'recurring_months' => 12,
            'recurring_monthly_cents' => 100_000,
        ]);

        CommercialSale::query()->create([
            'code' => 'VENDA-META-REC',
            'proposal_id' => $proposal->id,
            'client_name' => 'Cliente Recorrente',
            'status' => CommercialSale::STATUS_ABERTA,
            'total_cents' => 1_200_000,
            'is_recurring' => true,
            'recurring_months' => 12,
            'recurring_monthly_cents' => 100_000,
            'sold_at' => now(),
            'created_by' => $admin->id,
            'seller_id' => $admin->id,
        ]);

        $this->createSale($admin, 'PROP-META-PONT', 'VENDA-META-PONT', 250_000, now());

        $home = app(AdminHomeDashboardBuilder::class)->build();

        // Recorrente 12× R$1.000 → conta R$1.000; pontual R$2.500 → total R$3.500.
        $this->assertSame(350_000, $home['monthly_goal']['current_cents']);
    }

    private function createSale(
        User $admin,
        string $proposalCode,
        string $saleCode,
        int $totalCents,
        mixed $soldAt,
    ): void {
        $proposal = CommercialProposal::query()->create([
            'code' => $proposalCode,
            'client_name' => 'Cliente '.$proposalCode,
            'is_closed' => true,
            'total_final_cents' => $totalCents,
        ]);

        CommercialSale::query()->create([
            'code' => $saleCode,
            'proposal_id' => $proposal->id,
            'client_name' => 'Cliente '.$proposalCode,
            'status' => CommercialSale::STATUS_ABERTA,
            'total_cents' => $totalCents,
            'sold_at' => $soldAt,
            'created_by' => $admin->id,
            'seller_id' => $admin->id,
        ]);
    }
}
