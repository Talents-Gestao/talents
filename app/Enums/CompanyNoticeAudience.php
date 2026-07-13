<?php

declare(strict_types=1);

namespace App\Enums;

enum CompanyNoticeAudience: string
{
    /** Avisos dirigidos a utilizadores de uma empresa (portal /client). */
    case Company = 'company';

    /** Avisos internos dirigidos aos administradores Talents (portal /admin). */
    case Talents = 'talents';

    public function label(): string
    {
        return match ($this) {
            self::Company => 'empresa',
            self::Talents => 'Talents',
        };
    }
}
