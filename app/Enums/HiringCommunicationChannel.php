<?php

declare(strict_types=1);

namespace App\Enums;

enum HiringCommunicationChannel: string
{
    case Email = 'email';
    case Whatsapp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'E-mail',
            self::Whatsapp => 'WhatsApp',
        };
    }
}
