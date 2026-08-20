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

    public function test_owner_creating_proposal_alone_defaults_to_zero_commission(): void
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

    public function test_proposal_with_eligible_seller_uses_settings_default_commission(): void
    {
        $this->withoutVite();

        CommercialSetting::current()->update(['default_commission_percent' => 10]);
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $seller = User::factory()->create(['is_commercial' => true, 'is_active' => true, 'is_owner' => false]);
        $product = $this->makeProduct();

        $this->actingAs($admin)->post(route('admin.comercial.propostas.store'), [
            ...$this->payload($product->id),
            'seller_id' => $seller->id,
        ])->assertRedirect(route('admin.comercial.propostas.index'));

        $proposal = CommercialProposal::query()->first();
        $this->assertNotNull($proposal);
        $this->assertSame($seller->id, $proposal->seller_id);
        $this->assertSame(10.0, (float) $proposal->commission_percent);
        $this->assertSame(15_770, (int) $proposal->commission_cents);
    }

    public function test_owner_can_enable_commission_as_exception(): void
    {
        $this->withoutVite();

        CommercialSetting::current()->update(['default_commission_percent' => 10]);
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $product = $this->makeProduct();

        $this->actingAs($admin)->post(route('admin.comercial.propostas.store'), [
            ...$this->payload($product->id),
            'pay_commission' => true,
            'commission_percent' => 7,
        ])->assertRedirect(route('admin.comercial.propostas.index'));

        $proposal = CommercialProposal::query()->first();
        $this->assertNotNull($proposal);
        $this->assertSame(7.0, (float) $proposal->commission_percent);
        $this->assertSame(11_039, (int) $proposal->commission_cents);
    }

    public function test_explicit_sem_comissao_zeros_even_with_seller(): void
    {
        $this->withoutVite();

        CommercialSetting::current()->update(['default_commission_percent' => 10]);
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $seller = User::factory()->create(['is_commercial' => true, 'is_active' => true, 'is_owner' => false]);
        $product = $this->makeProduct();

        $this->actingAs($admin)->post(route('admin.comercial.propostas.store'), [
            ...$this->payload($product->id),
            'seller_id' => $seller->id,
            'pay_commission' => false,
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
