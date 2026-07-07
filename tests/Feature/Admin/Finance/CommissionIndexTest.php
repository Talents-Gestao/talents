<?php

namespace Tests\Feature\Admin\Finance;

use App\Models\CommercialCommission;
use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use App\Models\User;
use App\Services\Commercial\ProposalSaleConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommissionIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_commissions_index_lists_pending_first(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $seller = User::factory()->create(['is_commercial' => true, 'name' => 'Vendedor A']);

        $paidSale = $this->createSaleWithCommission($seller, 5000, 500);
        $paidCommission = $paidSale->commission;
        $paidCommission->update([
            'status' => CommercialCommission::STATUS_PAGA,
            'paid_at' => now()->subDay(),
        ]);

        $pendingSale = $this->createSaleWithCommission($seller, 10000, 1000);
        $pendingCommission = $pendingSale->commission;

        $this->actingAs($admin)
            ->get(route('admin.financeiro.comissoes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Financeiro/Comissoes/Index')
                ->where('summary.pending_cents', 1000)
                ->where('summary.pending_count', 1)
                ->where('summary.paid_cents', 500)
                ->has('commissions.data', 2)
                ->where('commissions.data.0.id', $pendingCommission->id)
                ->where('commissions.data.1.id', $paidCommission->id)
            );
    }

    public function test_commission_update_redirects_back_to_index(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $seller = User::factory()->create(['is_commercial' => true]);
        $sale = $this->createSaleWithCommission($seller, 8000, 800);
        $commission = $sale->commission;

        $this->actingAs($admin)
            ->from(route('admin.financeiro.comissoes.index'))
            ->patch(route('admin.financeiro.comissoes.update', $commission), [
                'status' => CommercialCommission::STATUS_PAGA,
                'paid_at' => now()->toDateString(),
                'notes' => 'Repasse PIX',
            ])
            ->assertRedirect(route('admin.financeiro.comissoes.index'))
            ->assertSessionHas('success');

        $commission->refresh();
        $this->assertSame(CommercialCommission::STATUS_PAGA, $commission->status);
        $this->assertSame('Repasse PIX', $commission->notes);
    }

    private function createSaleWithCommission(User $seller, int $totalCents, int $commissionCents): CommercialSale
    {
        $proposal = CommercialProposal::create([
            'code' => 'PROP-2026-'.fake()->unique()->numerify('####'),
            'client_name' => 'Cliente Teste',
            'employee_count' => 10,
            'seller_id' => $seller->id,
            'total_final_cents' => $totalCents,
            'commission_percent' => 10,
            'commission_cents' => $commissionCents,
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        return app(ProposalSaleConversionService::class)->convert($proposal, [
            'payment_method' => 'pix',
            'installments_count' => 1,
            'first_due_date' => now()->addDays(10)->toDateString(),
        ]);
    }
}
