<?php

namespace Tests\Unit;

use App\Enums\CommercialProductPricingType;
use App\Models\CommercialProduct;
use App\Models\CommercialProposal;
use App\Models\CommercialSetting;
use App\Services\CommercialPricingService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class CommercialPricingServiceTest extends TestCase
{
    private function defaultSettings(): CommercialSetting
    {
        return tap(new CommercialSetting(), function (CommercialSetting $s) {
            $s->default_commission_percent = 5;
        });
    }

    private function makeProduct(int $id, string $slug, CommercialProductPricingType $type, array $config): CommercialProduct
    {
        return tap(new CommercialProduct(), function (CommercialProduct $p) use ($id, $slug, $type, $config) {
            $p->id = $id;
            $p->name = $slug;
            $p->slug = $slug;
            $p->pricing_type = $type;
            $p->pricing_config = $config;
            $p->is_active = true;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $selections
     */
    private function calculateWithProducts(array $selections, int $employees, Collection $products): array
    {
        $svc = new CommercialPricingService();

        return $svc->calculate([
            'employee_count' => $employees,
            'catalog_products' => $selections,
            '_catalog_products' => $products,
        ], $this->defaultSettings());
    }

    public function test_fixed_product_is_included_in_total(): void
    {
        $product = $this->makeProduct(1, 'devolutiva', CommercialProductPricingType::Fixed, [
            'amount_cents' => 157700,
        ]);

        $r = $this->calculateWithProducts([
            ['product_id' => 1, 'enabled' => true],
        ], 2, collect([$product]));

        $this->assertSame(157700, $r['total_catalog_products_cents']);
        $this->assertSame(157700, $r['total_final_cents']);
        $this->assertSame(0, $r['total_pesquisas_cents']);
        $this->assertCount(1, $r['catalog_lines']);
    }

    public function test_per_employee_product_multiplies_by_headcount(): void
    {
        $product = $this->makeProduct(2, 'pesquisas', CommercialProductPricingType::PerEmployee, [
            'cents_per_employee' => 12700,
        ]);

        $r = $this->calculateWithProducts([
            ['product_id' => 2, 'enabled' => true],
        ], 3, collect([$product]));

        $this->assertSame(38100, $r['total_catalog_products_cents']);
        $this->assertSame(38100, $r['total_final_cents']);
    }

    public function test_threshold_multiplier_doubles_above_limit(): void
    {
        $product = $this->makeProduct(3, 'palestras', CommercialProductPricingType::ThresholdMultiplier, [
            'base_cents' => 157700,
            'threshold_employees' => 30,
            'multiplier' => 2,
        ]);

        $r = $this->calculateWithProducts([
            ['product_id' => 3, 'enabled' => true],
        ], 31, collect([$product]));

        $this->assertSame(315400, $r['total_catalog_products_cents']);
    }

    public function test_no_products_selected_returns_zero(): void
    {
        $svc = new CommercialPricingService();
        $r = $svc->calculate(['employee_count' => 50], $this->defaultSettings());

        $this->assertSame(0, $r['total_final_cents']);
        $this->assertSame(0, $r['commission_cents']);
    }

    public function test_commission_uses_settings_default(): void
    {
        $settings = $this->defaultSettings();
        $settings->default_commission_percent = 10;

        $product = $this->makeProduct(4, 'profiler', CommercialProductPricingType::PerEmployee, [
            'cents_per_employee' => 32700,
        ]);

        $svc = new CommercialPricingService();
        $r = $svc->calculate([
            'employee_count' => 1,
            'catalog_products' => [['product_id' => 4, 'enabled' => true]],
            '_catalog_products' => collect([$product]),
        ], $settings);

        $this->assertSame(10.0, $r['commission_percent']);
        $this->assertSame(3270, $r['commission_cents']);
    }

    public function test_calculate_preserving_legacy_adds_stored_legacy_totals(): void
    {
        $proposal = tap(new CommercialProposal(), function (CommercialProposal $p) {
            $p->svc_pesquisas = true;
            $p->total_pesquisas_cents = 12700;
            $p->total_profiler_cents = 0;
            $p->total_devolutiva_cents = 0;
            $p->total_nr1_cents = 0;
            $p->total_nr1_implantacao_cents = 0;
            $p->total_contratacao_cents = 0;
            $p->total_direcionamento_cents = 0;
            $p->total_palestras_cents = 0;
        });

        $product = $this->makeProduct(5, 'novo-produto', CommercialProductPricingType::Fixed, [
            'amount_cents' => 50000,
        ]);

        $svc = new CommercialPricingService();
        $r = $svc->calculatePreservingLegacy($proposal, [
            'employee_count' => 1,
            'catalog_products' => [['product_id' => 5, 'enabled' => true]],
            '_catalog_products' => collect([$product]),
        ], $this->defaultSettings());

        $this->assertSame(12700, $r['total_pesquisas_cents']);
        $this->assertSame(50000, $r['total_catalog_products_cents']);
        $this->assertSame(62700, $r['total_final_cents']);
    }

    public function test_flexible_rates_with_discount(): void
    {
        $product = $this->makeProduct(10, 'direcionamento', CommercialProductPricingType::FlexibleRates, [
            'rates' => [
                'hour' => ['enabled' => true, 'cents_per_unit' => 54700],
                'quantity' => ['enabled' => false, 'cents_per_unit' => 0],
                'unit' => ['enabled' => false, 'cents_per_unit' => 0],
            ],
        ]);

        $r = $this->calculateWithProducts([
            [
                'product_id' => 10,
                'enabled' => true,
                'rate_mode' => 'hour',
                'units' => 10,
                'adjustment' => 'discount',
                'discount_type' => 'percent',
                'discount_percent' => 10,
            ],
        ], 5, collect([$product]));

        $this->assertSame(492300, $r['total_catalog_products_cents']);
    }

    public function test_fixed_product_with_percent_discount(): void
    {
        $product = $this->makeProduct(12, 'devolutiva', CommercialProductPricingType::Fixed, [
            'amount_cents' => 100000,
        ]);

        $r = $this->calculateWithProducts([
            [
                'product_id' => 12,
                'enabled' => true,
                'adjustment' => 'discount',
                'discount_type' => 'percent',
                'discount_percent' => 15,
            ],
        ], 1, collect([$product]));

        $this->assertSame(85000, $r['total_catalog_products_cents']);
        $this->assertStringContainsString('Desconto 15%', $r['catalog_lines'][0]['detail']);
        $this->assertSame(100000, $r['catalog_lines'][0]['options']['subtotal_cents']);
    }

    public function test_fixed_product_with_value_discount(): void
    {
        $product = $this->makeProduct(13, 'pacote', CommercialProductPricingType::Fixed, [
            'amount_cents' => 200000,
        ]);

        $r = $this->calculateWithProducts([
            [
                'product_id' => 13,
                'enabled' => true,
                'adjustment' => 'discount',
                'discount_type' => 'value',
                'discount_value_cents' => 25000,
            ],
        ], 1, collect([$product]));

        $this->assertSame(175000, $r['total_catalog_products_cents']);
        $this->assertStringContainsString('Desconto R$ 250,00', $r['catalog_lines'][0]['detail']);
        $this->assertSame(25000, $r['catalog_lines'][0]['options']['discount_value_cents']);
    }

    public function test_flexible_rates_with_value_discount(): void
    {
        $product = $this->makeProduct(14, 'consultoria', CommercialProductPricingType::FlexibleRates, [
            'rates' => [
                'hour' => ['enabled' => true, 'cents_per_unit' => 10000],
                'quantity' => ['enabled' => false, 'cents_per_unit' => 0],
                'unit' => ['enabled' => false, 'cents_per_unit' => 0],
            ],
        ]);

        $r = $this->calculateWithProducts([
            [
                'product_id' => 14,
                'enabled' => true,
                'rate_mode' => 'hour',
                'units' => 5,
                'adjustment' => 'discount',
                'discount_type' => 'value',
                'discount_value_cents' => 3000,
            ],
        ], 1, collect([$product]));

        $this->assertSame(47000, $r['total_catalog_products_cents']);
    }

    public function test_flexible_rates_bonus_is_zero_but_included(): void
    {
        $product = $this->makeProduct(11, 'consultoria', CommercialProductPricingType::FlexibleRates, [
            'rates' => [
                'hour' => ['enabled' => true, 'cents_per_unit' => 10000],
                'quantity' => ['enabled' => false, 'cents_per_unit' => 0],
                'unit' => ['enabled' => false, 'cents_per_unit' => 0],
            ],
        ]);

        $svc = new CommercialPricingService();
        $r = $svc->calculate([
            'employee_count' => 1,
            'catalog_products' => [[
                'product_id' => 11,
                'enabled' => true,
                'rate_mode' => 'hour',
                'units' => 2,
                'adjustment' => 'bonus',
            ]],
            '_catalog_products' => collect([$product]),
        ], $this->defaultSettings());

        $this->assertSame(0, $r['total_catalog_products_cents']);
        $this->assertCount(1, $r['catalog_lines']);
        $this->assertStringContainsString('Bonificação', $r['catalog_lines'][0]['detail']);
    }
}
