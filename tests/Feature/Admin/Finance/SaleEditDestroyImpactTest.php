<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Finance;

use App\Models\CommercialCommission;
use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use App\Models\CommercialSaleInstallment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleEditDestroyImpactTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_payload_includes_finance_impact(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $sale = $this->saleWithProposalAndCommission();

        $this->actingAs($admin)
            ->get(route('admin.financeiro.vendas.edit', $sale))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Finance/Sales/Form')
                ->where('mode', 'edit')
                ->where('sale.finance_impact.requires_warning', true)
                ->where('sale.finance_impact.has_proposal', true)
                ->where('sale.finance_impact.has_commission', true)
                ->has('sale.finance_impact.items', 3));
    }

    public function test_can_update_sale_with_paid_installment_without_recalculating_total(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $sale = $this->saleWithProposalAndCommission();
        $sale->installments()->first()?->update([
            'status' => CommercialSaleInstallment::STATUS_PAGO,
            'paid_at' => now(),
            'paid_amount_cents' => 12_000,
        ]);
        $sale->recalculateStatus();

        $this->actingAs($admin)
            ->put(route('admin.financeiro.vendas.update', $sale), [
                'client_name' => 'Cliente após edição',
                'sold_at' => '2026-08-20',
                'notes' => 'Nota com impacto',
            ])
            ->assertRedirect(route('admin.financeiro.vendas.show', $sale));

        $fresh = $sale->fresh();
        $this->assertSame('Cliente após edição', $fresh->client_name);
        $this->assertSame(12_000, (int) $fresh->total_cents);
        $this->assertTrue($fresh->installments()->where('status', CommercialSaleInstallment::STATUS_PAGO)->exists());
    }

    public function test_can_delete_sale_removes_installments_and_commission_keeps_proposal(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $sale = $this->saleWithProposalAndCommission();
        $saleId = $sale->id;
        $proposalId = (int) $sale->proposal_id;
        $installmentId = (int) $sale->installments()->value('id');
        $commissionId = (int) $sale->commission()->value('id');

        $this->actingAs($admin)
            ->delete(route('admin.financeiro.vendas.destroy', $sale))
            ->assertRedirect(route('admin.financeiro.vendas.index'))
            ->assertSessionHas('success')
            ->assertSessionHas('info');

        $this->assertFalse(CommercialSale::query()->whereKey($saleId)->exists());
        $this->assertFalse(CommercialSaleInstallment::query()->whereKey($installmentId)->exists());
        $this->assertFalse(CommercialCommission::query()->whereKey($commissionId)->exists());

        $proposal = CommercialProposal::query()->find($proposalId);
        $this->assertNotNull($proposal);
        $this->assertTrue((bool) $proposal->is_closed);
        $this->assertFalse($proposal->sale()->exists());
    }

    private function saleWithProposalAndCommission(): CommercialSale
    {
        $seller = User::factory()->superAdmin()->create([
            'is_commercial' => true,
            'is_active' => true,
            'commission_percent' => 10,
        ]);

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-SALE-IMPACT',
            'client_name' => 'Cliente Impacto',
            'employee_count' => 5,
            'total_final_cents' => 12_000,
            'is_closed' => true,
            'closed_at' => now(),
            'list_status' => 'closed',
            'seller_id' => $seller->id,
        ]);

        $sale = CommercialSale::query()->create([
            'code' => 'VENDA-2026-IMPACT',
            'proposal_id' => $proposal->id,
            'client_name' => $proposal->client_name,
            'seller_id' => $seller->id,
            'total_cents' => 12_000,
            'commission_percent' => 10,
            'commission_cents' => 1_200,
            'payment_method' => 'pix',
            'installments_count' => 1,
            'status' => CommercialSale::STATUS_ABERTA,
            'sold_at' => now(),
        ]);

        CommercialSaleInstallment::query()->create([
            'sale_id' => $sale->id,
            'number' => 1,
            'amount_cents' => 12_000,
            'due_date' => now()->addDays(10)->toDateString(),
            'method' => 'pix',
            'status' => CommercialSaleInstallment::STATUS_PENDENTE,
        ]);

        CommercialCommission::query()->create([
            'sale_id' => $sale->id,
            'seller_id' => $seller->id,
            'amount_cents' => 1_200,
            'status' => 'a_pagar',
        ]);

        return $sale->fresh(['proposal', 'commission', 'installments']);
    }
}
