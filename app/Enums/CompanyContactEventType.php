<?php

declare(strict_types=1);

namespace App\Enums;

enum CompanyContactEventType: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Reviewed = 'reviewed';
    case Archived = 'archived';
    case Restored = 'restored';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Contato criado',
            self::Updated => 'Contato alterado',
            self::Reviewed => 'Contato conferido',
            self::Archived => 'Contato arquivado',
            self::Restored => 'Contato restaurado',
        };
    }
}
