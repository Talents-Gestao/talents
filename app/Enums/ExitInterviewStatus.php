<?php

declare(strict_types=1);

namespace App\Enums;

enum ExitInterviewStatus: string
{
    case Draft = 'draft';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Completed => 'Concluída',
        };
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return [
            self::Draft,
            self::Completed,
        ];
    }
}
