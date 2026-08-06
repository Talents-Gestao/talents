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

    public function test_pdf_omits_publico_atendido_when_flag_is_false(): void
    {
        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-PAY-0005',
            'client_name' => 'Cliente Sem Público',
            'employee_count' => 25,
            'include_publico_atendido' => false,
            'include_minimum_stay' => true,
            'total_final_cents' => 1000,
            'is_closed' => false,
            'payment_method' => CommercialProposalPaymentMethod::Pix,
        ]);

        $html = view('reports.commercial-proposal', [
            'proposal' => $proposal->load('seller'),
            'settings' => CommercialSetting::current(),
            'logoBase64' => '',
            'butterflyBase64' => '',
            'services' => [],
            'optionalSections' => [],
            'validityDate' => now()->addDays(7),
        ])->render();

        $this->assertStringNotContainsString('Público Atendido', $html);
        $this->assertStringNotContainsString('25 colaboradores', $html);
    }

    public function test_pdf_includes_publico_atendido_when_flag_is_true(): void
    {
        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-PAY-0006',
            'client_name' => 'Cliente Com Público',
            'employee_count' => 25,
            'include_publico_atendido' => true,
            'include_minimum_stay' => false,
            'total_final_cents' => 1000,
            'is_closed' => false,
            'payment_method' => CommercialProposalPaymentMethod::Pix,
        ]);

        $html = view('reports.commercial-proposal', [
            'proposal' => $proposal->load('seller'),
            'settings' => CommercialSetting::current(),
            'logoBase64' => '',
            'butterflyBase64' => '',
            'services' => [],
            'optionalSections' => [],
            'validityDate' => now()->addDays(7),
        ])->render();

        $this->assertStringContainsString('Público Atendido', $html);
        $this->assertStringContainsString('25 colaboradores', $html);
        $this->assertStringNotContainsString('Permanência mínima de 90', $html);
    }

    public function test_pdf_omits_minimum_stay_when_flag_is_false(): void
    {
        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-PAY-0007',
            'client_name' => 'Cliente Sem Permanência',
            'employee_count' => 5,
            'include_publico_atendido' => true,
            'include_minimum_stay' => false,
            'total_final_cents' => 2000,
            'is_closed' => false,
            'payment_method' => CommercialProposalPaymentMethod::Boleto,
        ]);

        $html = view('reports.commercial-proposal', [
            'proposal' => $proposal->load('seller'),
            'settings' => CommercialSetting::current(),
            'logoBase64' => '',
            'butterflyBase64' => '',
            'services' => [],
            'optionalSections' => [],
            'validityDate' => now()->addDays(7),
        ])->render();

        $this->assertStringContainsString('Condições de Pagamento', $html);
        $this->assertStringNotContainsString('Permanência mínima de 90', $html);
        $this->assertStringContainsString('Prazo de validade desta proposta', $html);
    }

    public function test_pdf_flags_are_persisted_on_store(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $this->actingAs($admin)
            ->post(route('admin.comercial.propostas.store'), [
                'client_name' => 'Empresa Flags PDF',
                'employee_count' => 12,
                'is_closed' => false,
                'payment_method' => 'boleto',
                'include_publico_atendido' => false,
                'include_minimum_stay' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('commercial_proposals', [
            'client_name' => 'Empresa Flags PDF',
            'include_publico_atendido' => false,
            'include_minimum_stay' => false,
        ]);
    }

    public function test_pdf_footer_contacts_line_omits_phone(): void
    {
        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-PAY-0008',
            'client_name' => 'Cliente Rodapé',
            'employee_count' => 4,
            'total_final_cents' => 1000,
            'is_closed' => false,
            'payment_method' => CommercialProposalPaymentMethod::Pix,
        ]);

        $html = view('reports.commercial-proposal', [
            'proposal' => $proposal->load('seller'),
            'settings' => CommercialSetting::current(),
            'logoBase64' => '',
            'butterflyBase64' => '',
            'services' => [],
            'optionalSections' => [],
            'validityDate' => now()->addDays(7),
        ])->render();

        $this->assertMatchesRegularExpression(
            '/class="footer-contacts">\s*[^<]+?\|\s*[^|]+?\|\s*[^|<]+?\s*<\/p>/s',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/class="footer-contacts">[^<]*97570-3032/s',
            $html
        );
        $this->assertStringContainsString('WhatsApp (11) 97570-3032', $html);
    }

    public function test_pdf_includes_notes_when_filled(): void
    {
        $notes = "Prazo de implantação a combinar.\nVisita técnica inclusa.";

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-PAY-0009',
            'client_name' => 'Cliente Com Observações',
            'employee_count' => 6,
            'total_final_cents' => 2500,
            'is_closed' => false,
            'payment_method' => CommercialProposalPaymentMethod::Boleto,
            'notes' => $notes,
        ]);

        $html = view('reports.commercial-proposal', [
            'proposal' => $proposal->load('seller'),
            'settings' => CommercialSetting::current(),
            'logoBase64' => '',
            'butterflyBase64' => '',
            'services' => [],
            'optionalSections' => [],
            'validityDate' => now()->addDays(7),
        ])->render();

        $this->assertStringContainsString('<h2>Observações</h2>', $html);
        $this->assertStringContainsString('Prazo de implantação a combinar.', $html);
        $this->assertStringContainsString('Visita técnica inclusa.', $html);
    }

    public function test_pdf_omits_notes_section_when_empty(): void
    {
        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-PAY-0010',
            'client_name' => 'Cliente Sem Observações',
            'employee_count' => 2,
            'total_final_cents' => 800,
            'is_closed' => false,
            'payment_method' => CommercialProposalPaymentMethod::Pix,
            'notes' => null,
        ]);

        $html = view('reports.commercial-proposal', [
            'proposal' => $proposal->load('seller'),
            'settings' => CommercialSetting::current(),
            'logoBase64' => '',
            'butterflyBase64' => '',
            'services' => [
                [
                    'label' => 'Serviço teste',
                    'detail' => null,
                    'observation' => 'Observação só do serviço',
                    'value_cents' => 800,
                    'description' => null,
                ],
            ],
            'optionalSections' => [],
            'validityDate' => now()->addDays(7),
        ])->render();

        $this->assertStringNotContainsString('<h2>Observações</h2>', $html);
        $this->assertStringContainsString('Observação só do serviço', $html);
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
