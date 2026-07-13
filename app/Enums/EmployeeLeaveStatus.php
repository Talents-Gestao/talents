<?php

declare(strict_types=1);

namespace App\Enums;

enum EmployeeLeaveStatus: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Agendado',
            self::InProgress => 'Em férias',
            self::Completed => 'Concluído',
            self::Cancelled => 'Cancelado',
        };
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return [
            self::Scheduled,
            self::InProgress,
            self::Completed,
            self::Cancelled,
        ];
    }
}
