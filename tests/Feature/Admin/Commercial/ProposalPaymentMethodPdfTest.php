<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Commercial;

use App\Enums\CommercialProposalPaymentMethod;
use App\Models\CommercialProposal;
use App\Models\CommercialSetting;
use App\Models\User;
use App\Services\CommercialProposalPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProposalPaymentMethodPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_must_choose_payment_method_when_creating_proposal(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $this->actingAs($admin)
            ->post(route('admin.comercial.propostas.store'), [
                'client_name' => 'Cliente Sem Pagamento',
                'employee_count' => 10,
                'is_closed' => false,
            ])
            ->assertSessionHasErrors('payment_method');
    }

    public function test_pdf_uses_payment_method_from_proposal_not_hardcoded_default(): void
    {
        $settings = CommercialSetting::current();
        $settings->pdf_condicoes_pagamento = '• Texto global que NÃO deve aparecer;';
        $settings->save();

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-PAY-0001',
            'client_name' => 'Cliente PIX',
            'employee_count' => 8,
            'total_final_cents' => 10000,
            'is_closed' => false,
            'payment_method' => CommercialProposalPaymentMethod::Pix,
        ]);

        $html = view('reports.commercial-proposal', [
            'proposal' => $proposal->load('seller'),
            'settings' => $settings->fresh(),
            'logoBase64' => '',
            'butterflyBase64' => '',
            'services' => [],
            'optionalSections' => [],
            'validityDate' => now()->addDays(7),
        ])->render();

        $this->assertStringContainsString('Pagamento via PIX', $html);
        $this->assertStringNotContainsString('Texto global que NÃO deve aparecer', $html);
        $this->assertDoesNotMatchRegularExpression('/\d+x\s*R\$/i', $html);
    }

    public function test_pdf_without_payment_method_does_not_force_credit_card_default(): void
    {
        $settings = CommercialSetting::current();
        $settings->pdf_condicoes_pagamento = null;
        $settings->save();

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-PAY-0002',
            'client_name' => 'Cliente Legado',
            'employee_count' => 3,
            'total_final_cents' => 5000,
            'is_closed' => false,
            'payment_method' => null,
        ]);

        $html = view('reports.commercial-proposal', [
            'proposal' => $proposal->load('seller'),
            'settings' => $settings->fresh(),
            'logoBase64' => '',
            'butterflyBase64' => '',
            'services' => [],
            'optionalSections' => [],
            'validityDate' => now()->addDays(7),
        ])->render();

        $this->assertStringContainsString('Condições de Pagamento', $html);
        $this->assertStringContainsString('Permanência mínima de 90 (noventa) dias (3 meses) para cancelamento do plano.', $html);
        $this->assertStringContainsString('Prazo de validade desta proposta', $html);
        $this->assertDoesNotMatchRegularExpression('/\d+x\s*R\$/i', $html);
    }

    public function test_credit_payment_condition_does_not_list_installment_unit_amounts(): void
    {
        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-PAY-0004',
            'client_name' => 'Cliente Crédito',
            'employee_count' => 10,
            'total_final_cents' => 315400,
            'is_closed' => false,
            'payment_method' => CommercialProposalPaymentMethod::Credito,
        ]);

        $html = view('reports.commercial-proposal', [
            'proposal' => $proposal->load('seller'),
            'settings' => CommercialSetting::current(),
            'logoBase64' => '',
            'butterflyBase64' => '',
            'services' => [
                [
                    'key' => 'palestras',
                    'label' => 'Palestras e Treinamentos',
                    'detail' => 'Pacote ampliado',
                    'value_cents' => 315400,
                    'description' => '',
                ],
            ],
            'optionalSections' => [],
            'validityDate' => now()->addDays(7),
        ])->render();

        $this->assertStringContainsString('Pagamento no cartão de crédito, com possibilidade de parcelamento', $html);
        $this->assertStringContainsString('R$ 3.154,00', $html);
        $this->assertDoesNotMatchRegularExpression('/\d+x\s*R\$/i', $html);
        $this->assertSame(
            'Pagamento no cartão de crédito, com possibilidade de parcelamento. Os valores deste instrumento referem-se ao montante total contratado.',
            CommercialProposalPaymentMethod::Credito->contractText(),
        );
    }

    public function test_saved_payment_method_is_persisted(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $this->actingAs($admin)
            ->post(route('admin.comercial.propostas.store'), [
                'client_name' => 'Empresa Boleto',
                'employee_count' => 12,
                'is_closed' => false,
                'payment_method' => 'boleto',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('commercial_proposals', [
            'client_name' => 'Empresa Boleto',
            'payment_method' => 'boleto',
        ]);
    }

    public function test_pdf_service_generates_with_selected_payment_method(): void
    {
        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-PAY-0003',
            'client_name' => 'Cliente Débito',
            'employee_count' => 4,
            'total_final_cents' => 8000,
            'is_closed' => false,
            'payment_method' => CommercialProposalPaymentMethod::Debito,
        ]);

        $pdf = app(CommercialProposalPdfService::class)->generate($proposal);

        $this->assertNotEmpty($pdf->output());
    }
}
