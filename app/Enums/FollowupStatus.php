<?php

declare(strict_types=1);

namespace App\Enums;

/** Status do marco (e da fila) do pós-contratação. Calculado por App\Support\Hiring\FollowupMilestoneStatus. */
enum FollowupStatus: string
{
    case PendingAdmission = 'pending_admission';
    case Scheduled = 'scheduled';
    case Due = 'due';
    case Overdue = 'overdue';
    case Paused = 'paused';
    case Completed = 'completed';
    case Terminated = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::PendingAdmission => 'Admissão pendente',
            self::Scheduled => 'Programado',
            self::Due => 'Devido',
            self::Overdue => 'Vencido · revisar',
            self::Paused => 'Pausado',
            self::Completed => 'Concluído',
            self::Terminated => 'Desligado',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(static fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
