<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Situação de uma comunicação. Nunca afirma entrega nem leitura: «sent» só quer dizer que o servidor
 * de e-mail aceitou a mensagem, e «copied» que o texto do WhatsApp foi copiado.
 */
enum HiringCommunicationStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Failed = 'failed';
    case Copied = 'copied';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Sent => 'Enviado ao servidor de e-mail',
            self::Failed => 'Falhou',
            self::Copied => 'Texto copiado',
        };
    }

    /** Já saiu (e-mail aceito pelo servidor ou texto copiado): o mesmo conteúdo não sai de novo sem «Enviar novamente». */
    public function isDone(): bool
    {
        return $this === self::Sent || $this === self::Copied;
    }
}
