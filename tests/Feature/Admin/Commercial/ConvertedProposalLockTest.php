<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Commercial;

use App\Enums\CommercialProductPricingType;
use App\Models\CommercialProduct;
use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use App\Models\FinancePaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConvertedProposalLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_update_closed_proposal_without_sale(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $product = $this->makeProduct();
        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-CLOSED-EDIT',
            'client_name' => 'Cliente Fechado',
            'employee_count' => 8,
            'total_final_cents' => 12_000,
            'is_closed' => true,
            'closed_at' => now(),
            'list_status' => 'closed',
            'payment_method_id' => FinancePaymentMethod::query()->where('slug', 'pix')->value('id'),
        ]);

        $this->actingAs($admin)
            ->put(route('admin.comercial.propostas.update', $proposal), [
                'client_name' => 'Cliente com data alterada',
                'employee_count' => 8,
                'is_closed' => true,
                'payment_method_id' => $proposal->payment_method_id,
                'catalog_products' => [
                    ['product_id' => $product->id, 'enabled' => true],
                ],
            ])
            ->assertRedirect();

        $this->assertSame('Cliente com data alterada', $proposal->fresh()->client_name);
        $this->assertTrue((bool) $proposal->fresh()->is_closed);
    }

    public function test_can_update_proposal_after_sale_conversion(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $product = $this->makeProduct();
        $proposal = $this->convertedProposal($product);

        $this->actingAs($admin)
            ->put(route('admin.comercial.propostas.update', $proposal), [
                'client_name' => 'Nome alterado após venda',
                'employee_count' => 99,
                'is_closed' => true,
                'payment_method_id' => $proposal->payment_method_id,
                'palestra_event_date' => '2026-09-15',
                'catalog_products' => [
                    ['product_id' => $product->id, 'enabled' => true],
                ],
            ])
            ->assertRedirect();

        $fresh = $proposal->fresh();
        $this->assertSame('Nome alterado após venda', $fresh->client_name);
        $this->assertSame(99, (int) $fresh->employee_count);
        $this->assertTrue($fresh->sale()->exists());
    }

    public function test_can_delete_proposal_after_sale_conversion_and_removes_sale(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $proposal = $this->convertedProposal($this->makeProduct());
        $saleId = $proposal->sale->id;

        $this->actingAs($admin)
            ->delete(route('admin.comercial.propostas.destroy', $proposal))
            ->assertRedirect(route('admin.comercial.propostas.index'));

        $this->assertFalse(CommercialProposal::query()->whereKey($proposal->id)->exists());
        $this->assertFalse(CommercialSale::query()->whereKey($saleId)->exists());
    }

    public function test_edit_payload_includes_finance_impact_when_converted(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $proposal = $this->convertedProposal($this->makeProduct());

        $this->actingAs($admin)
            ->get(route('admin.comercial.propostas.edit', $proposal))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Commercial/Proposals/Form')
                ->where('proposal.finance_impact.requires_warning', true)
                ->where('proposal.finance_impact.has_sale', true)
                ->has('proposal.finance_impact.items', 3));
    }

    private function convertedProposal(CommercialProduct $product): CommercialProposal
    {
        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-LOCK-0001',
            'client_name' => 'Cliente Convertido',
            'employee_count' => 8,
            'total_final_cents' => 12_000,
            'is_closed' => true,
            'closed_at' => now(),
            'list_status' => 'closed',
            'payment_method_id' => FinancePaymentMethod::query()->where('slug', 'pix')->value('id'),
        ]);

        CommercialSale::query()->create([
            'code' => 'VENDA-2026-LOCK',
            'proposal_id' => $proposal->id,
            'client_name' => $proposal->client_name,
            'total_cents' => 12_000,
            'payment_method' => 'pix',
            'installments_count' => 1,
            'status' => CommercialSale::STATUS_ABERTA,
            'sold_at' => now(),
        ]);

        return $proposal->fresh(['sale']);
    }

    private function makeProduct(): CommercialProduct
    {
        return CommercialProduct::query()->create([
            'name' => 'Palestras e Treinamentos',
            'slug' => 'palestras-edit-lock',
            'pricing_type' => CommercialProductPricingType::Fixed,
            'pricing_config' => ['amount_cents' => 12_000],
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }
}
