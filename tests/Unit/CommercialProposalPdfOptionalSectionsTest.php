<?php

namespace Tests\Unit;

use App\Models\CommercialProposal;
use App\Models\CommercialSetting;
use App\Support\CommercialProposalPdfOptionalSections;
use Tests\TestCase;

class CommercialProposalPdfOptionalSectionsTest extends TestCase
{
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

    public function test_normalize_selection_defaults_missing_keys_to_false(): void
    {
        $normalized = CommercialProposalPdfOptionalSections::normalizeSelection([
            CommercialProposalPdfOptionalSections::KEY_METAMORFOSE_PESSOAL => true,
        ]);

        $this->assertTrue($normalized[CommercialProposalPdfOptionalSections::KEY_METAMORFOSE_PESSOAL]);
        $this->assertFalse($normalized[CommercialProposalPdfOptionalSections::KEY_PLATAFORMA_MODULOS]);
    }
}
