<?php

namespace App\Enums;

enum StrategicCalendarItemKind: string
{
    case Event = 'event';
    case Ritual = 'ritual';
    case Birthday = 'birthday';

    public function label(): string
    {
        return match ($this) {
            self::Event => 'Evento',
            self::Ritual => 'Ritual',
            self::Birthday => 'Aniversário',
        };
    }
}
