<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\CommercialContract;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CommercialContractZapSignStatusTest extends TestCase
{
    #[DataProvider('signedStatuses')]
    public function test_is_zap_sign_signed_recognizes_known_statuses(string $status): void
    {
        $contract = new CommercialContract([
            'zapsign_status' => $status,
            'zapsign_document_token' => 'tok',
        ]);

        $this->assertTrue($contract->isZapSignSigned());
        $this->assertSame('Assinado', $contract->zapSignStatusLabel());
    }

    public static function signedStatuses(): array
    {
        return [
            ['signed'],
            ['assinado'],
            ['completed'],
            ['concluido'],
            ['concluído'],
        ];
    }

    public function test_was_sent_without_signed_status(): void
    {
        $contract = new CommercialContract([
            'zapsign_status' => 'pending',
            'zapsign_document_token' => 'tok',
            'zapsign_sent_at' => now(),
        ]);

        $this->assertTrue($contract->wasSentToZapSign());
        $this->assertFalse($contract->isZapSignSigned());
        $this->assertSame('Enviado (aguardando assinatura)', $contract->zapSignStatusLabel());
    }

    public function test_pdf_only_label(): void
    {
        $contract = new CommercialContract([
            'zapsign_status' => null,
            'zapsign_document_token' => null,
            'zapsign_sent_at' => null,
        ]);

        $this->assertFalse($contract->wasSentToZapSign());
        $this->assertFalse($contract->isZapSignSigned());
        $this->assertSame('PDF gerado', $contract->zapSignStatusLabel());
    }
}
