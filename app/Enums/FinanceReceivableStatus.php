<?php

declare(strict_types=1);

namespace App\Enums;

enum FinanceReceivableStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Paid => 'Recebido',
            self::Cancelled => 'Cancelado',
        };
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return [self::Pending, self::Paid, self::Cancelled];
    }
}
