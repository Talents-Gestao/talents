<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\CommercialProposal;
use App\Models\CommercialSetting;
use App\Support\CommercialProposalPdfOptionalSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialProposalPdfOptionalSectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_proposal_returns_only_enabled_sections_in_order(): void
    {
        $proposal = new CommercialProposal([
            'pdf_optional_sections' => [
                CommercialProposalPdfOptionalSections::KEY_ANALISE_SALARIAL => true,
                CommercialProposalPdfOptionalSections::KEY_TREINAMENTOS => true,
            ],
        ]);

        $sections = CommercialProposalPdfOptionalSections::forProposal($proposal, new CommercialSetting);

        $this->assertCount(2, $sections);
        $this->assertSame('analise_salarial', $sections[0]['key']);
        $this->assertSame('treinamentos', $sections[1]['key']);
        $this->assertNotEmpty($sections[0]['text']);
    }

    public function test_for_proposal_returns_empty_when_nothing_selected(): void
    {
        $proposal = new CommercialProposal([
            'pdf_optional_sections' => [
                CommercialProposalPdfOptionalSections::KEY_ANALISE_SALARIAL => false,
                CommercialProposalPdfOptionalSections::KEY_TREINAMENTOS => false,
            ],
        ]);

        $this->assertSame(
            [],
            CommercialProposalPdfOptionalSections::forProposal($proposal, new CommercialSetting)
        );
    }

    public function test_for_proposal_returns_empty_when_selection_is_null(): void
    {
        $proposal = new CommercialProposal([
            'pdf_optional_sections' => null,
        ]);

        $this->assertSame(
            [],
            CommercialProposalPdfOptionalSections::forProposal($proposal, new CommercialSetting)
        );
    }

    public function test_normalize_selection_defaults_missing_keys_to_false(): void
    {
        $normalized = CommercialProposalPdfOptionalSections::normalizeSelection([
            CommercialProposalPdfOptionalSections::KEY_METAMORFOSE_PESSOAL => true,
        ]);

        $this->assertTrue($normalized[CommercialProposalPdfOptionalSections::KEY_METAMORFOSE_PESSOAL]);
        $this->assertFalse($normalized[CommercialProposalPdfOptionalSections::KEY_PLATAFORMA_MODULOS]);
    }

    public function test_pdf_omits_complementary_section_when_optional_sections_empty(): void
    {
        $settings = CommercialSetting::current();

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-OPT-EMPTY',
            'client_name' => 'Cliente Sem Complementares',
            'employee_count' => 5,
            'total_final_cents' => 1000,
            'is_closed' => false,
            'pdf_optional_sections' => null,
        ]);

        $optionalSections = CommercialProposalPdfOptionalSections::forProposal($proposal, $settings);

        $html = view('reports.commercial-proposal', [
            'proposal' => $proposal->load('seller'),
            'settings' => $settings,
            'logoBase64' => '',
            'butterflyBase64' => '',
            'services' => [
                [
                    'label' => 'Consultoria',
                    'observation' => null,
                    'value_cents' => 1000,
                    'description' => null,
                    'discount_cents' => 0,
                ],
            ],
            'optionalSections' => $optionalSections,
            'validityDate' => now()->addDays(7),
        ])->render();

        $this->assertSame([], $optionalSections);
        $this->assertStringNotContainsString('Projetos e serviços complementares', $html);
        $this->assertStringNotContainsString('Os itens abaixo não estão inclusos', $html);
        $this->assertStringContainsString('Consultoria', $html);
        $this->assertStringContainsString('Honorário Total', $html);
    }

    public function test_pdf_includes_complementary_section_when_option_selected(): void
    {
        $settings = CommercialSetting::current();

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-OPT-ONE',
            'client_name' => 'Cliente Com Complementar',
            'employee_count' => 5,
            'total_final_cents' => 1000,
            'is_closed' => false,
            'pdf_optional_sections' => [
                CommercialProposalPdfOptionalSections::KEY_ANALISE_SALARIAL => true,
            ],
        ]);

        $optionalSections = CommercialProposalPdfOptionalSections::forProposal($proposal, $settings);

        $html = view('reports.commercial-proposal', [
            'proposal' => $proposal->load('seller'),
            'settings' => $settings,
            'logoBase64' => '',
            'butterflyBase64' => '',
            'services' => [],
            'optionalSections' => $optionalSections,
            'validityDate' => now()->addDays(7),
        ])->render();

        $this->assertCount(1, $optionalSections);
        $this->assertStringContainsString('Projetos e serviços complementares', $html);
        $this->assertStringContainsString('Os itens abaixo não estão inclusos', $html);
        $this->assertStringContainsString('Análise Salarial', $html);
        $this->assertStringContainsString('Pesquisa Salarial Regional', $html);
    }

    public function test_pdf_omits_section_when_passed_only_blank_optional_items(): void
    {
        $settings = CommercialSetting::current();

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-OPT-BLANK',
            'client_name' => 'Cliente Itens Vazios',
            'employee_count' => 3,
            'total_final_cents' => 500,
            'is_closed' => false,
            'pdf_optional_sections' => null,
        ]);

        $html = view('reports.commercial-proposal', [
            'proposal' => $proposal->load('seller'),
            'settings' => $settings,
            'logoBase64' => '',
            'butterflyBase64' => '',
            'services' => [],
            'optionalSections' => [
                ['key' => 'x', 'label' => '   ', 'text' => ''],
                ['key' => 'y', 'label' => '', 'text' => "  \n"],
            ],
            'validityDate' => now()->addDays(7),
        ])->render();

        $this->assertStringNotContainsString('Projetos e serviços complementares', $html);
    }
}
