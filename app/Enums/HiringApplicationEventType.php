<?php

declare(strict_types=1);

namespace App\Enums;

enum HiringApplicationEventType: string
{
    case Added = 'added';
    case Moved = 'moved';
    case Note = 'note';

    public function label(): string
    {
        return match ($this) {
            self::Added => 'Incluído na vaga',
            self::Moved => 'Mudança de etapa',
            self::Note => 'Anotação',
        };
    }
}
