<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Finance;

use App\Actions\Commercial\RefreshCommissionPayout;
use App\Http\Controllers\Admin\Finance\SaleController;
use App\Models\CommercialCommission;
use App\Models\CommercialCommissionPayment;
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

    public function test_can_delete_sale_with_unpaid_commission_removes_installments_and_commission_keeps_proposal(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $sale = $this->saleWithProposalAndCommission();
        $this->assertSame(CommercialCommission::STATUS_A_PAGAR, $sale->commission->status);
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

    public function test_cannot_delete_sale_with_fully_paid_commission(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $sale = $this->saleWithProposalAndCommission();
        $this->addPayment($sale->commission, 1_200);

        $this->actingAs($admin)
            ->from(route('admin.financeiro.vendas.show', $sale))
            ->delete(route('admin.financeiro.vendas.destroy', $sale))
            ->assertRedirect(route('admin.financeiro.vendas.show', $sale))
            ->assertSessionHasErrors(['sale' => SaleController::PAID_COMMISSION_DESTROY_MESSAGE]);

        $this->assertTrue(CommercialSale::query()->whereKey($sale->id)->exists());
        $this->assertSame(1, CommercialSaleInstallment::query()->where('sale_id', $sale->id)->count());
        $this->assertSame(
            CommercialCommission::STATUS_PAGA,
            CommercialCommission::query()->whereKey($sale->commission->id)->value('status'),
        );
    }

    public function test_cannot_delete_sale_with_partially_paid_commission(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $sale = $this->saleWithProposalAndCommission();
        $this->addPayment($sale->commission, 100);
        $this->assertSame(CommercialCommission::STATUS_PARCIAL, $sale->commission->fresh()->status);

        $this->actingAs($admin)
            ->delete(route('admin.financeiro.vendas.destroy', $sale))
            ->assertSessionHasErrors(['sale' => SaleController::PAID_COMMISSION_DESTROY_MESSAGE]);

        $this->assertTrue(CommercialSale::query()->whereKey($sale->id)->exists());
    }

    public function test_cannot_delete_sale_when_only_extra_commission_has_payment(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $sale = $this->saleWithProposalAndCommission();
        $extra = User::factory()->superAdmin()->create(['commission_percent' => 5]);
        $extraCommission = CommercialCommission::query()->create([
            'sale_id' => $sale->id,
            'seller_id' => $extra->id,
            'amount_cents' => 600,
            'status' => CommercialCommission::STATUS_A_PAGAR,
        ]);
        $this->addPayment($extraCommission, 300);

        $this->actingAs($admin)
            ->delete(route('admin.financeiro.vendas.destroy', $sale))
            ->assertSessionHasErrors('sale');

        $this->assertTrue(CommercialSale::query()->whereKey($sale->id)->exists());
        $this->assertSame(2, CommercialCommission::query()->where('sale_id', $sale->id)->count());
    }

    public function test_can_delete_sale_whose_commission_payments_were_all_undone(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $sale = $this->saleWithProposalAndCommission();
        $payment = $this->addPayment($sale->commission, 1_200);
        $payment->forceFill(['deleted_by' => $admin->id])->save();
        $payment->delete();

        $this->actingAs($admin)
            ->delete(route('admin.financeiro.vendas.destroy', $sale))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.financeiro.vendas.index'));

        $this->assertFalse(CommercialSale::query()->whereKey($sale->id)->exists());
        $this->assertFalse(CommercialCommissionPayment::withTrashed()->whereKey($payment->id)->exists());
    }

    public function test_sales_index_exposes_paid_commissions_count(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $sale = $this->saleWithProposalAndCommission();
        $this->addPayment($sale->commission, 100);

        $this->actingAs($admin)
            ->get(route('admin.financeiro.vendas.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Finance/Sales/Index')
                ->where('sales.data.0.id', $sale->id)
                ->where('sales.data.0.paid_commissions_count', 1)
                ->where('destroyBlockedMessage', SaleController::PAID_COMMISSION_DESTROY_MESSAGE));
    }

    private function addPayment(CommercialCommission $commission, int $cents): CommercialCommissionPayment
    {
        $payment = $commission->payments()->create([
            'amount_cents' => $cents,
            'paid_at' => now(),
        ]);
        app(RefreshCommissionPayout::class)->forCommission($commission);

        return $payment;
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
