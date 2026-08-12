<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * @deprecated Preferir FinancePaymentMethod (CRUD Financeiro). Mantido para referência
 * de textos legados e migração de slugs (credito/debito → cartao).
 */
enum CommercialProposalPaymentMethod: string
{
    case Boleto = 'boleto';
    case Pix = 'pix';
    case Credito = 'credito';
    case Debito = 'debito';

    public function label(): string
    {
        return match ($this) {
            self::Boleto => 'Boleto',
            self::Pix => 'PIX',
            self::Credito => 'Cartão de crédito',
            self::Debito => 'Cartão de débito',
        };
    }

    /**
     * Texto em bullet exibido na secção «Condições de Pagamento» do PDF.
     * Não incluir valor por parcela (ex.: «2x R$ 500,00») — só o total nos demais campos.
     */
    public function pdfBullet(): string
    {
        return match ($this) {
            self::Boleto => '• Pagamento via boleto bancário;',
            self::Pix => '• Pagamento via PIX;',
            self::Credito => '• Pagamento no cartão de crédito, com possibilidade de parcelamento;',
            self::Debito => '• Pagamento no cartão de débito;',
        };
    }

    /**
     * Texto para placeholders de contrato (forma_pagamento).
     * Sem discriminação do valor de cada parcela.
     */
    public function contractText(): string
    {
        return match ($this) {
            self::Boleto => 'Pagamento via boleto bancário.',
            self::Pix => 'Pagamento via PIX.',
            self::Credito => 'Pagamento no cartão de crédito, com possibilidade de parcelamento. Os valores deste instrumento referem-se ao montante total contratado.',
            self::Debito => 'Pagamento no cartão de débito.',
        };
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return [self::Boleto, self::Pix, self::Credito, self::Debito];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $method) => [
                'value' => $method->value,
                'label' => $method->label(),
            ],
            self::all(),
        );
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
