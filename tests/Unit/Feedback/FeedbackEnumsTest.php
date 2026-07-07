<?php

declare(strict_types=1);

namespace Tests\Unit\Feedback;

use App\Enums\FeedbackSessionStatus;
use App\Enums\FeedbackSignatureRole;
use PHPUnit\Framework\TestCase;

class FeedbackEnumsTest extends TestCase
{
    public function test_session_status_labels_are_in_portuguese(): void
    {
        $this->assertSame('Rascunho', FeedbackSessionStatus::Draft->label());
        $this->assertSame('Em preenchimento', FeedbackSessionStatus::InProgress->label());
        $this->assertSame('Aguardando assinaturas', FeedbackSessionStatus::AwaitingSignatures->label());
        $this->assertSame('Concluído', FeedbackSessionStatus::Completed->label());
        $this->assertSame('Cancelado', FeedbackSessionStatus::Cancelled->label());
    }

    public function test_signature_role_labels(): void
    {
        $this->assertSame('Colaborador(a)', FeedbackSignatureRole::Employee->label());
        $this->assertSame('Líder', FeedbackSignatureRole::Leader->label());
    }
}
