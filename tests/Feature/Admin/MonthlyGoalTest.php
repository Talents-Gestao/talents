<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdminDashboardSettings;
use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use App\Models\CommercialSaleInstallment;
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
        $admin = User::factory()->superAdmin()->create([
            'is_owner' => true,
            'is_commercial' => true,
        ]);

        config(['talents.dashboard.monthly_revenue_goal_cents' => 2_000_000]);

        $this->createSaleWithInstallment(
            admin: $admin,
            proposalCode: 'PROP-META-1',
            saleCode: 'VENDA-META-1',
            amountCents: 1_000_000,
            soldAt: now(),
            paidAt: now(),
        );

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
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

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

    public function test_monthly_goal_current_excludes_payments_from_other_months(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'is_owner' => true,
            'is_commercial' => true,
        ]);

        $this->createSaleWithInstallment($admin, 'PROP-META-CUR', 'VENDA-META-CUR', 800_000, now(), now());
        $this->createSaleWithInstallment($admin, 'PROP-META-OLD', 'VENDA-META-OLD', 9_000_000, now()->subMonth(), now()->subMonth());
        $this->createSaleWithInstallment($admin, 'PROP-META-FUT', 'VENDA-META-FUT', 7_000_000, now(), now()->addMonth(), paid: false);

        $home = app(AdminHomeDashboardBuilder::class)->build();

        $this->assertSame(800_000, $home['monthly_goal']['current_cents']);
    }

    public function test_monthly_goal_counts_only_paid_installments_in_current_month(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'is_owner' => true,
            'is_commercial' => true,
        ]);

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-META-REC',
            'client_name' => 'Cliente Recorrente',
            'is_closed' => true,
            'total_final_cents' => 1_200_000,
            'is_recurring' => true,
            'recurring_months' => 12,
            'recurring_monthly_cents' => 100_000,
        ]);

        $sale = CommercialSale::query()->create([
            'code' => 'VENDA-META-REC',
            'proposal_id' => $proposal->id,
            'client_name' => 'Cliente Recorrente',
            'status' => CommercialSale::STATUS_PARCIAL,
            'total_cents' => 1_200_000,
            'is_recurring' => true,
            'recurring_months' => 12,
            'recurring_monthly_cents' => 100_000,
            'sold_at' => now()->subMonth(),
            'created_by' => $admin->id,
            'seller_id' => $admin->id,
        ]);

        CommercialSaleInstallment::query()->create([
            'sale_id' => $sale->id,
            'number' => 1,
            'amount_cents' => 100_000,
            'paid_amount_cents' => 100_000,
            'due_date' => now()->subMonth()->toDateString(),
            'paid_at' => now()->subMonth(),
            'status' => CommercialSaleInstallment::STATUS_PAGO,
        ]);
        CommercialSaleInstallment::query()->create([
            'sale_id' => $sale->id,
            'number' => 2,
            'amount_cents' => 100_000,
            'paid_amount_cents' => 100_000,
            'due_date' => now()->toDateString(),
            'paid_at' => now(),
            'status' => CommercialSaleInstallment::STATUS_PAGO,
        ]);
        CommercialSaleInstallment::query()->create([
            'sale_id' => $sale->id,
            'number' => 3,
            'amount_cents' => 100_000,
            'due_date' => now()->addMonth()->toDateString(),
            'status' => CommercialSaleInstallment::STATUS_PENDENTE,
        ]);

        $this->createSaleWithInstallment($admin, 'PROP-META-PONT', 'VENDA-META-PONT', 250_000, now(), now());

        $home = app(AdminHomeDashboardBuilder::class)->build();

        // Recorrente: só a parcela paga neste mês (R$1.000) + pontual paga (R$2.500).
        $this->assertSame(350_000, $home['monthly_goal']['current_cents']);
    }

    public function test_monthly_goal_excludes_sales_from_non_commercial_sellers(): void
    {
        $commercial = User::factory()->superAdmin()->create([
            'is_owner' => true,
            'is_commercial' => true,
        ]);
        $nonCommercial = User::factory()->superAdmin()->create([
            'is_owner' => true,
            'is_commercial' => false,
        ]);

        $this->createSaleWithInstallment($commercial, 'PROP-META-COM', 'VENDA-META-COM', 500_000, now(), now());
        $this->createSaleWithInstallment($nonCommercial, 'PROP-META-ADM', 'VENDA-META-ADM', 9_000_000, now(), now());

        $home = app(AdminHomeDashboardBuilder::class)->build();

        $this->assertSame(500_000, $home['monthly_goal']['current_cents']);
    }

    public function test_monthly_goal_counts_payment_from_sale_closed_in_previous_month(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'is_owner' => true,
            'is_commercial' => true,
        ]);

        $this->createSaleWithInstallment(
            admin: $admin,
            proposalCode: 'PROP-META-PREV',
            saleCode: 'VENDA-META-PREV',
            amountCents: 600_000,
            soldAt: now()->subMonths(2),
            paidAt: now(),
        );

        $home = app(AdminHomeDashboardBuilder::class)->build();

        $this->assertSame(600_000, $home['monthly_goal']['current_cents']);
    }

    private function createSaleWithInstallment(
        User $admin,
        string $proposalCode,
        string $saleCode,
        int $amountCents,
        mixed $soldAt,
        mixed $paidAt = null,
        bool $paid = true,
    ): CommercialSale {
        $proposal = CommercialProposal::query()->create([
            'code' => $proposalCode,
            'client_name' => 'Cliente '.$proposalCode,
            'is_closed' => true,
            'total_final_cents' => $amountCents,
        ]);

        $sale = CommercialSale::query()->create([
            'code' => $saleCode,
            'proposal_id' => $proposal->id,
            'client_name' => 'Cliente '.$proposalCode,
            'status' => $paid ? CommercialSale::STATUS_QUITADA : CommercialSale::STATUS_ABERTA,
            'total_cents' => $amountCents,
            'sold_at' => $soldAt,
            'created_by' => $admin->id,
            'seller_id' => $admin->id,
        ]);

        CommercialSaleInstallment::query()->create([
            'sale_id' => $sale->id,
            'number' => 1,
            'amount_cents' => $amountCents,
            'paid_amount_cents' => $paid ? $amountCents : null,
            'due_date' => ($paidAt ?? $soldAt)->toDateString(),
            'paid_at' => $paid ? ($paidAt ?? now()) : null,
            'status' => $paid ? CommercialSaleInstallment::STATUS_PAGO : CommercialSaleInstallment::STATUS_PENDENTE,
        ]);

        return $sale;
    }
}
