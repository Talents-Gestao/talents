<?php

declare(strict_types=1);

namespace App\Enums;

enum EmploymentCycleEventType: string
{
    case Created = 'created';
    case AdmissionConfirmed = 'admission_confirmed';
    case Updated = 'updated';
    case Paused = 'paused';
    case Resumed = 'resumed';
    case Terminated = 'terminated';
    case Readmitted = 'readmitted';
    case MilestoneSkipped = 'milestone_skipped';
    case InvitationRevoked = 'invitation_revoked';
    case Note = 'note';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Vínculo registrado',
            self::AdmissionConfirmed => 'Admissão confirmada',
            self::Updated => 'Dados do vínculo alterados',
            self::Paused => 'Acompanhamento pausado',
            self::Resumed => 'Acompanhamento retomado',
            self::Terminated => 'Desligamento registrado',
            self::Readmitted => 'Readmissão registrada',
            self::MilestoneSkipped => 'Marco pulado',
            self::InvitationRevoked => 'Convite revogado',
            self::Note => 'Anotação',
        };
    }
}
