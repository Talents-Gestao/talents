<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Commercial;

use App\Enums\CommercialProductPricingType;
use App\Models\CommercialContractTemplate;
use App\Models\CommercialProduct;
use App\Models\CommercialProposal;
use App\Models\CommercialProposalProductLine;
use App\Models\FinancePaymentMethod;
use App\Models\User;
use App\Services\Commercial\ContractPlaceholderService;
use App\Support\CanonicalContractTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractFromProposalServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_rejects_proposal_without_priced_products(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $this->actingAs($admin)
            ->post(route('admin.comercial.propostas.store'), [
                'client_name' => 'Cliente Sem Produto',
                'employee_count' => 10,
                'is_closed' => false,
                'payment_method_id' => $this->pixId(),
            ])
            ->assertSessionHasErrors('catalog_products');
    }

    public function test_placeholders_list_catalog_product_labels(): void
    {
        $product = $this->fixedProduct('Palestras e Treinamentos', 'palestras-contrato');

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-CTR-0001',
            'client_name' => 'Cliente Com Serviço',
            'employee_count' => 10,
            'total_final_cents' => 157_700,
            'is_closed' => false,
        ]);

        CommercialProposalProductLine::query()->create([
            'commercial_proposal_id' => $proposal->id,
            'commercial_product_id' => $product->id,
            'options' => [],
            'label_snapshot' => $product->name,
            'detail_snapshot' => 'Valor fixo',
            'total_cents' => 157_700,
        ]);

        $map = app(ContractPlaceholderService::class)->placeholders($proposal->fresh('catalogLines.product'));

        $this->assertSame('Palestras e Treinamentos', $map['servicos_rotulos']);
        $this->assertSame('R$ 1.577,00', $map['total_reais']);
        $this->assertStringContainsString('Palestras e Treinamentos', $map['servicos_lista_html']);
        $this->assertStringNotContainsString('nenhum serviço selecionado', mb_strtolower($map['servicos_rotulos']));
    }

    public function test_placeholders_include_recurring_when_catalog_is_empty(): void
    {
        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-CTR-REC',
            'client_name' => 'Cliente Recorrente',
            'employee_count' => 8,
            'is_recurring' => true,
            'recurring_months' => 12,
            'recurring_monthly_cents' => 150_000,
            'total_final_cents' => 1_800_000,
            'is_closed' => false,
        ]);

        $map = app(ContractPlaceholderService::class)->placeholders($proposal->fresh());

        $this->assertSame('Acompanhamento recorrente', $map['servicos_rotulos']);
        $this->assertSame('R$ 18.000,00', $map['total_reais']);
        $this->assertStringContainsString('Acompanhamento recorrente', $map['servicos_lista_html']);
    }

    public function test_placeholders_ignore_leftover_catalog_when_proposal_is_recurring(): void
    {
        $product = $this->fixedProduct('Produto leftover', 'produto-leftover');

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-CTR-REC-CAT',
            'client_name' => 'Cliente Recorrente Catálogo',
            'employee_count' => 8,
            'is_recurring' => true,
            'recurring_months' => 12,
            'recurring_monthly_cents' => 150_000,
            'total_final_cents' => 1_800_000,
            'is_closed' => false,
        ]);

        CommercialProposalProductLine::query()->create([
            'commercial_proposal_id' => $proposal->id,
            'commercial_product_id' => $product->id,
            'options' => [],
            'label_snapshot' => $product->name,
            'detail_snapshot' => 'Valor fixo',
            'total_cents' => 157_700,
        ]);

        $map = app(ContractPlaceholderService::class)->placeholders($proposal->fresh('catalogLines.product'));

        $this->assertSame('Acompanhamento recorrente', $map['servicos_rotulos']);
        $this->assertStringContainsString('Acompanhamento recorrente', $map['servicos_lista_html']);
        $this->assertStringNotContainsString('Produto leftover', $map['servicos_lista_html']);
    }

    public function test_cannot_generate_contract_when_proposal_has_no_services(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $template = $this->consultoriaTemplate();

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-CTR-EMPTY',
            'client_name' => 'Cliente Vazio',
            'employee_count' => 5,
            'total_final_cents' => 0,
            'is_closed' => false,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.comercial.propostas.index'))
            ->post(route('admin.comercial.propostas.contratos.store', $proposal), [
                'template_id' => $template->id,
            ])
            ->assertRedirect(route('admin.comercial.propostas.index'))
            ->assertSessionHas('error');
    }

    public function test_generated_contract_html_contains_catalog_service_list(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $template = $this->consultoriaTemplate();
        $product = $this->fixedProduct('NR-1 — Mapeamento', 'nr1-contrato');

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-CTR-OK',
            'client_name' => 'Cliente Com Catálogo',
            'employee_count' => 12,
            'total_final_cents' => 200_000,
            'is_closed' => false,
            'payment_method_id' => $this->pixId(),
        ]);

        CommercialProposalProductLine::query()->create([
            'commercial_proposal_id' => $proposal->id,
            'commercial_product_id' => $product->id,
            'options' => [],
            'label_snapshot' => $product->name,
            'detail_snapshot' => '12 funcionários',
            'total_cents' => 200_000,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.comercial.propostas.contratos.store', $proposal), [
                'template_id' => $template->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('contract_id');

        $contractId = session('contract_id');
        $this->assertNotNull($contractId);

        $html = \App\Models\CommercialContract::query()->findOrFail($contractId)->html_snapshot;
        $this->assertStringContainsString('NR-1 — Mapeamento', $html);
        $this->assertStringNotContainsString('Lista resumida dos serviços contratados: —', $html);
        $this->assertStringNotContainsString('nenhum serviço selecionado', mb_strtolower($html));
    }

    private function pixId(): int
    {
        return (int) FinancePaymentMethod::query()->where('slug', 'pix')->value('id');
    }

    private function fixedProduct(string $name, string $slug): CommercialProduct
    {
        return CommercialProduct::query()->create([
            'name' => $name,
            'slug' => $slug,
            'pricing_type' => CommercialProductPricingType::Fixed,
            'pricing_config' => ['amount_cents' => 157_700],
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function consultoriaTemplate(): CommercialContractTemplate
    {
        return CommercialContractTemplate::query()->create([
            'name' => 'Consultoria - Padrão Talents',
            'source_type' => 'html',
            'body_html' => CanonicalContractTemplates::all()['Consultoria - Padrão Talents'],
            'is_active' => true,
        ]);
    }
}
