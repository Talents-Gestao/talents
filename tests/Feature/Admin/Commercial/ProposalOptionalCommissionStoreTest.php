<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Commercial;

use App\Enums\CommercialProductPricingType;
use App\Models\CommercialProduct;
use App\Models\CommercialProposal;
use App\Models\CommercialSetting;
use App\Models\FinancePaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProposalOptionalCommissionStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_proposal_without_seller_has_zero_commission(): void
    {
        $this->withoutVite();

        CommercialSetting::current()->update(['default_commission_percent' => 10]);
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $product = $this->makeProduct();

        $this->actingAs($admin)->post(route('admin.comercial.propostas.store'), $this->payload($product->id))
            ->assertRedirect(route('admin.comercial.propostas.index'));

        $proposal = CommercialProposal::query()->first();
        $this->assertNotNull($proposal);
        $this->assertNull($proposal->seller_id);
        $this->assertSame(0.0, (float) $proposal->commission_percent);
        $this->assertSame(0, (int) $proposal->commission_cents);
    }

    public function test_proposal_uses_seller_fixed_commission_percent(): void
    {
        $this->withoutVite();

        CommercialSetting::current()->update(['default_commission_percent' => 99]);
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $seller = User::factory()->create([
            'is_commercial' => true,
            'is_active' => true,
            'is_owner' => false,
            'commission_percent' => 10,
        ]);
        $product = $this->makeProduct();

        $this->actingAs($admin)->post(route('admin.comercial.propostas.store'), [
            ...$this->payload($product->id),
            'seller_id' => $seller->id,
            // Pedidos legados são ignorados — usa a % do vendedor.
            'pay_commission' => false,
            'commission_percent' => 7,
        ])->assertRedirect(route('admin.comercial.propostas.index'));

        $proposal = CommercialProposal::query()->first();
        $this->assertNotNull($proposal);
        $this->assertSame($seller->id, $proposal->seller_id);
        $this->assertSame(10.0, (float) $proposal->commission_percent);
        $this->assertSame(15_770, (int) $proposal->commission_cents);
    }

    public function test_seller_with_zero_percent_stores_zero_commission(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $seller = User::factory()->create([
            'is_commercial' => true,
            'is_active' => true,
            'commission_percent' => 0,
        ]);
        $product = $this->makeProduct();

        $this->actingAs($admin)->post(route('admin.comercial.propostas.store'), [
            ...$this->payload($product->id),
            'seller_id' => $seller->id,
            'pay_commission' => true,
            'commission_percent' => 10,
        ])->assertRedirect(route('admin.comercial.propostas.index'));

        $proposal = CommercialProposal::query()->first();
        $this->assertNotNull($proposal);
        $this->assertSame(0.0, (float) $proposal->commission_percent);
        $this->assertSame(0, (int) $proposal->commission_cents);
    }

    private function makeProduct(): CommercialProduct
    {
        return CommercialProduct::query()->create([
            'name' => 'Palestras e Treinamentos',
            'slug' => 'palestras-comissao',
            'pricing_type' => CommercialProductPricingType::Fixed,
            'pricing_config' => ['amount_cents' => 157_700],
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(int $productId): array
    {
        return [
            'client_name' => 'Empresa Comissão Ltda',
            'employee_count' => 5,
            'is_closed' => false,
            'payment_method_id' => FinancePaymentMethod::query()->where('slug', 'pix')->value('id'),
            'catalog_products' => [
                [
                    'product_id' => $productId,
                    'enabled' => true,
                ],
            ],
        ];
    }
}
