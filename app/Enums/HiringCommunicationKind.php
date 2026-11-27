<?php

declare(strict_types=1);

namespace App\Enums;

enum HiringCommunicationKind: string
{
    case StageUpdate = 'stage_update';
    case Dossier = 'dossier';

    public function label(): string
    {
        return match ($this) {
            self::StageUpdate => 'Comunicação de etapa',
            self::Dossier => 'Envio do dossiê',
        };
    }
}
