<?php

// Cartão do quadro SUPORTE: «VALORES E CONTRATOS» (valores fixos, faixa por funcionário e produtos editáveis em Produtos).
// Cálculo por tipo já coberto em Unit/CommercialPricingServiceTest; aqui o fluxo da tela: editar → persistir → prévia.

namespace Tests\Feature\Acceptance\Support;

use App\Models\CommercialProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValuesAndContractsTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_fixed_and_per_employee_tier_products_persists_and_changes_the_proposal_total(): void
    {
        $admin = User::factory()->talentsOwner()->create();
        $this->actingAs($admin);

        $this->post(route('admin.comercial.products.store'), [
            'name' => 'Implantação',
            'pricing_type' => 'fixed',
            'pricing_config' => ['amount_cents' => 100000],
            'is_active' => true,
        ])->assertRedirect()->assertSessionHas('success');

        $this->post(route('admin.comercial.products.store'), [
            'name' => 'Pesquisa por colaborador',
            'pricing_type' => 'tiered_per_employee',
            'pricing_config' => [
                'tier1_max' => 5, 'tier1_cents' => 3000,
                'tier2_max' => 10, 'tier2_cents' => 2500,
                'tier3_max' => 20, 'tier3_cents' => 2000,
                'tier4_cents' => 1500,
            ],
            'is_active' => true,
        ])->assertRedirect();

        $fixed = CommercialProduct::query()->where('name', 'Implantação')->firstOrFail();
        $tiered = CommercialProduct::query()->where('name', 'Pesquisa por colaborador')->firstOrFail();

        $preview = fn () => $this->postJson(route('admin.comercial.propostas.preview'), [
            'employee_count' => 8,
            'catalog_products' => [
                ['product_id' => $fixed->id, 'enabled' => true],
                ['product_id' => $tiered->id, 'enabled' => true],
            ],
        ])->assertOk()->json('total_final_cents');

        // 1.000,00 + 8 × 25,00 (2ª faixa)
        $this->assertSame(120000, $preview());

        $this->put(route('admin.comercial.products.update', $fixed), [
            'name' => 'Implantação',
            'pricing_type' => 'fixed',
            'pricing_config' => ['amount_cents' => 150000],
            'is_active' => true,
        ])->assertRedirect()->assertSessionHas('success');

        $this->put(route('admin.comercial.products.update', $tiered), [
            'name' => 'Pesquisa por colaborador',
            'pricing_type' => 'tiered_per_employee',
            'pricing_config' => [
                'tier1_max' => 5, 'tier1_cents' => 3000,
                'tier2_max' => 10, 'tier2_cents' => 4000,
                'tier3_max' => 20, 'tier3_cents' => 2000,
                'tier4_cents' => 1500,
            ],
            'is_active' => true,
        ])->assertRedirect();

        $this->assertSame(150000, $fixed->fresh()->pricing_config['amount_cents']);
        $this->assertSame(4000, $tiered->fresh()->pricing_config['tier2_cents']);

        // 1.500,00 + 8 × 40,00
        $this->assertSame(182000, $preview());
    }
}
