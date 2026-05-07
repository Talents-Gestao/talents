<?php

namespace App\Enums;

enum TaskListVisibility: string
{
    case Internal = 'internal';
    case Company = 'company';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Interno (só Talents)',
            self::Company => 'Visível à empresa',
        };
    }
}
