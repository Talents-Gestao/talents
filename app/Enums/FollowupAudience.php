<?php

declare(strict_types=1);

namespace App\Enums;

enum FollowupAudience: string
{
    case Collaborator = 'collaborator';
    case Manager = 'manager';

    public function label(): string
    {
        return match ($this) {
            self::Collaborator => 'Colaborador',
            self::Manager => 'Gestor',
        };
    }
}
