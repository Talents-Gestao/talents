<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Commercial;

use App\Enums\CommercialProductPricingType;
use App\Models\CommercialProduct;
use App\Models\CommercialProposal;
use App\Models\CommercialProposalProductLine;
use App\Models\User;
use App\Services\Commercial\CommercialProposalServiceLines;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProposalProductObservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_observation_appears_in_pdf_service_lines(): void
    {
        $product = CommercialProduct::query()->create([
            'name' => 'Palestras e Treinamentos',
            'slug' => 'palestras',
            'pricing_type' => CommercialProductPricingType::Fixed,
            'pricing_config' => ['amount_cents' => 157700],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-TEST-0001',
            'client_name' => 'Cliente Teste',
            'employee_count' => 10,
            'total_final_cents' => 157700,
            'is_closed' => false,
        ]);

        CommercialProposalProductLine::query()->create([
            'commercial_proposal_id' => $proposal->id,
            'commercial_product_id' => $product->id,
            'options' => ['observation' => 'Evento presencial em São Paulo.'],
            'label_snapshot' => $product->name,
            'detail_snapshot' => 'Valor fixo',
            'total_cents' => 157700,
        ]);

        $lines = CommercialProposalServiceLines::forProposal($proposal->fresh('catalogLines.product'));

        $this->assertCount(1, $lines);
        $this->assertSame('Evento presencial em São Paulo.', $lines[0]['observation']);
    }

    public function test_pdf_preserves_newlines_in_product_observation(): void
    {
        $settings = \App\Models\CommercialSetting::current();

        $observation = "VAGA: Analista\nDescrição: Atuar com RH.\n\nVAGA: Assistente";

        $html = view('reports.commercial-proposal', [
            'proposal' => CommercialProposal::query()->create([
                'code' => 'PROP-OBS-NL-1',
                'client_name' => 'Cliente Observação',
                'employee_count' => 10,
                'total_final_cents' => 10000,
                'is_closed' => false,
            ])->load('seller'),
            'settings' => $settings,
            'logoBase64' => '',
            'butterflyBase64' => '',
            'services' => [
                [
                    'label' => 'Recrutamento',
                    'observation' => $observation,
                    'value_cents' => 10000,
                    'description' => null,
                    'discount_cents' => 0,
                ],
            ],
            'optionalSections' => [],
            'validityDate' => now()->addDays(7),
        ])->render();

        $this->assertStringContainsString('<strong>Observação:</strong><br>', $html);
        $this->assertStringContainsString('VAGA: Analista<br />', $html);
        $this->assertStringContainsString('Descrição: Atuar com RH.<br />', $html);
        $this->assertStringContainsString('VAGA: Assistente', $html);
        $this->assertStringNotContainsString(
            'VAGA: Analista Descrição: Atuar com RH. VAGA: Assistente',
            $html,
        );
    }

    public function test_admin_can_save_product_observation_on_proposal(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $product = CommercialProduct::query()->create([
            'name' => 'Palestras e Treinamentos',
            'slug' => 'palestras',
            'pricing_type' => CommercialProductPricingType::Fixed,
            'pricing_config' => ['amount_cents' => 157700],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.comercial.propostas.store'), [
            'client_name' => 'Empresa Observação',
            'employee_count' => 5,
            'is_closed' => false,
            'payment_method' => 'pix',
            'catalog_products' => [
                [
                    'product_id' => $product->id,
                    'enabled' => true,
                    'observation' => 'Turma de até 30 participantes.',
                ],
            ],
        ]);

        $response->assertRedirect();

        $line = CommercialProposalProductLine::query()->first();
        $this->assertNotNull($line);
        $this->assertSame('Turma de até 30 participantes.', $line->options['observation'] ?? null);
    }
}
