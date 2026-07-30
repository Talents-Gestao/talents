<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

class CommercialProposalDescriptionTextTest extends TestCase
{
    public function test_bullet_lines_do_not_leave_utf8_replacement_characters(): void
    {
        $text = <<<'TXT'
O que contempla:
• Palestra prática e estratégica voltada para lideres e gestores;
• Conteúdo customizado conforme necessidade da empresa;
• Material de apoio e certificado de participação;

Objetivo: Conscientizar e desenvolver lideres.
TXT;

        $html = view('reports.partials.description-text', ['text' => $text])->render();

        $this->assertStringNotContainsString('�', $html);
        $this->assertStringContainsString('<li>Palestra prática e estratégica voltada para lideres e gestores;</li>', $html);
        $this->assertStringContainsString('<li>Conteúdo customizado conforme necessidade da empresa;</li>', $html);
        $this->assertStringContainsString('<li>Material de apoio e certificado de participação;</li>', $html);
        $this->assertStringContainsString('<strong>Objetivo:</strong> Conscientizar e desenvolver lideres.', $html);
    }
}
