<?php

declare(strict_types=1);

namespace App\Enums;

/** Situação do vínculo do colaborador com a empresa num ciclo (admissão até o desligamento). */
enum EmploymentCycleStatus: string
{
    case PendingAdmission = 'pending_admission';
    case Active = 'active';
    case Paused = 'paused';
    case Terminated = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::PendingAdmission => 'Admissão pendente',
            self::Active => 'Ativo',
            self::Paused => 'Pausado',
            self::Terminated => 'Desligado',
        };
    }

    /** Ciclo em aberto: só um por colaborador e empresa. */
    public function isOpen(): bool
    {
        return $this !== self::Terminated;
    }
}
