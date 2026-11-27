<?php

declare(strict_types=1);

namespace App\Enums;

enum HiringVacancyStatus: string
{
    case Open = 'open';
    case Paused = 'paused';
    case Filled = 'filled';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Em andamento',
            self::Paused => 'Pausada',
            self::Filled => 'Concluída',
            self::Cancelled => 'Cancelada',
        };
    }

    /** Concluída ou cancelada: grava closed_at. */
    public function isClosed(): bool
    {
        return $this === self::Filled || $this === self::Cancelled;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $s) => ['value' => $s->value, 'label' => $s->label()],
            self::cases(),
        );
    }
}
