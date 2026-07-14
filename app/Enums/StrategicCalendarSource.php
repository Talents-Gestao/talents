<?php

declare(strict_types=1);

namespace App\Enums;

enum StrategicCalendarSource: string
{
    case Talents = 'talents';
    case Company = 'company';

    public function label(): string
    {
        return match ($this) {
            self::Talents => 'Talents',
            self::Company => 'Empresa',
        };
    }
}
