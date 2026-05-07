<?php

namespace App\Enums;

enum TaskCardVisibility: string
{
    case Internal = 'internal';
    case Company = 'company';
    case Inherit = 'inherit';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Interno',
            self::Company => 'Visível à empresa',
            self::Inherit => 'Seguir a lista',
        };
    }
}
