<?php

namespace App\Enums;

enum CompanyNoticeEventKind: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case DateChanged = 'date_changed';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'novo item',
            self::Updated => 'item atualizado',
            self::Deleted => 'item removido',
            self::DateChanged => 'data alterada',
        };
    }
}
