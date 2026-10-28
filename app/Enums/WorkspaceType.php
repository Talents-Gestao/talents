<?php

namespace App\Enums;

enum WorkspaceType: string
{
    case Talents = 'talents';
    case Company = 'company';
    case ExternalReview = 'external_review';

    public function label(): string
    {
        return match ($this) {
            self::Talents => 'Equipe Talents',
            self::Company => 'Empresa',
            self::ExternalReview => 'Revisão externa',
        };
    }
}
