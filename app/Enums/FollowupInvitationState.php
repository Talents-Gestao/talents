<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estados do convite, sempre separados: gerar, copiar, aceitar no servidor de e-mail e responder são fatos
 * diferentes. Copiar o link não é entregar; e-mail aceito pelo servidor não é entregue nem lido.
 */
enum FollowupInvitationState: string
{
    case Generated = 'generated';
    case Copied = 'copied';
    case Emailed = 'emailed';
    case Failed = 'failed';
    case Answered = 'answered';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Generated => 'Gerado',
            self::Copied => 'Link copiado',
            self::Emailed => 'Enviado ao servidor de e-mail',
            self::Failed => 'Falhou',
            self::Answered => 'Respondido',
            self::Expired => 'Expirado',
            self::Revoked => 'Revogado',
        };
    }
}
