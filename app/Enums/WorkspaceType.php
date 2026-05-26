<?php

namespace App\Enums;

enum WorkspaceType: string
{
    case Talents = 'talents';
    case Company = 'company';

    public function label(): string
    {
        return match ($this) {
            self::Talents => 'Equipe Talents',
            self::Company => 'Empresa',
        };
    }
}
