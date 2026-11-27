<?php

declare(strict_types=1);

namespace App\Enums;

/** Marcos do acompanhamento pós-contratação, contados da data de admissão. */
enum FollowupMoment: string
{
    case D15 = 'd15';
    case D30 = 'd30';
    case D60 = 'd60';
    case D90 = 'd90';

    public function days(): int
    {
        return match ($this) {
            self::D15 => 15,
            self::D30 => 30,
            self::D60 => 60,
            self::D90 => 90,
        };
    }

    public function label(): string
    {
        return 'D'.$this->days();
    }

    /**
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [self::D15, self::D30, self::D60, self::D90];
    }
}
