<?php

declare(strict_types=1);

namespace App\Enums;

enum FeedbackSignatureRole: string
{
    case Employee = 'employee';
    case Leader = 'leader';

    public function label(): string
    {
        return match ($this) {
            self::Employee => 'Colaborador(a)',
            self::Leader => 'Líder',
        };
    }
}
