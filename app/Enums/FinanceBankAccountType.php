<?php

declare(strict_types=1);

namespace App\Enums;

enum FinanceBankAccountType: string
{
    case Checking = 'checking';
    case Savings = 'savings';
    case Cash = 'cash';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Checking => 'Conta corrente',
            self::Savings => 'Poupança',
            self::Cash => 'Caixa',
            self::Other => 'Outra',
        };
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return [self::Checking, self::Savings, self::Cash, self::Other];
    }
}
