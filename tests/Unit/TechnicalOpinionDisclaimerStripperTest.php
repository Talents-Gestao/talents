<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\TechnicalOpinionDisclaimerStripper;
use PHPUnit\Framework\TestCase;

class TechnicalOpinionDisclaimerStripperTest extends TestCase
{
    public function test_strips_html_disclaimer_heading_and_following_paragraph(): void
    {
        $html = '<p>Conclusão da análise do cenário.</p><h2>Disclaimer</h2><p>O parecer é apoio à gestão de riscos psicossociais e não dispensa obrigações legais nem avaliação por equipe técnica competente quando exigida.</p>';

        $stripped = TechnicalOpinionDisclaimerStripper::strip($html);

        $this->assertNotNull($stripped);
        $this->assertStringContainsString('Conclusão da análise do cenário.', $stripped);
        $this->assertStringNotContainsString('Disclaimer', $stripped);
        $this->assertStringNotContainsString('obrigações legais', $stripped);
        $this->assertStringNotContainsString('equipe técnica competente', $stripped);
    }

    public function test_strips_inline_strong_disclaimer_paragraph(): void
    {
        $html = '<p>Recomenda-se revisar a dimensão Relacionamentos.</p><p><strong>Disclaimer:</strong> este parecer é apoio à decisão e não substitui avaliação por profissionais habilitados.</p>';

        $stripped = TechnicalOpinionDisclaimerStripper::strip($html);

        $this->assertNotNull($stripped);
        $this->assertStringContainsString('Recomenda-se revisar a dimensão Relacionamentos.', $stripped);
        $this->assertStringNotContainsString('Disclaimer', $stripped);
        $this->assertStringNotContainsString('profissionais habilitados', $stripped);
    }

    public function test_strips_markdown_disclaimer_section(): void
    {
        $markdown = <<<'MD'
## Recomendações

Priorizar o clima relacional no curto prazo.

## Disclaimer

O parecer é apoio à gestão de riscos psicossociais e não dispensa obrigações legais.
MD;

        $stripped = TechnicalOpinionDisclaimerStripper::strip($markdown);

        $this->assertNotNull($stripped);
        $this->assertStringContainsString('Priorizar o clima relacional', $stripped);
        $this->assertStringNotContainsString('Disclaimer', $stripped);
        $this->assertStringNotContainsString('obrigações legais', $stripped);
    }

    public function test_keeps_null_and_empty_as_null(): void
    {
        $this->assertNull(TechnicalOpinionDisclaimerStripper::strip(null));
        $this->assertNull(TechnicalOpinionDisclaimerStripper::strip('   '));
    }
}
