<?php

declare(strict_types=1);

namespace App\Enums;

enum MonthlyHighlightCategory: string
{
    case Produtividade = 'produtividade';
    case Pontualidade = 'pontualidade';
    case Engajamento = 'engajamento';
    case Atendimento = 'atendimento';
    case Comercial = 'comercial';
    case Indicacao = 'indicacao';

    public function label(): string
    {
        return match ($this) {
            self::Produtividade => 'Produtividade',
            self::Pontualidade => 'Pontualidade',
            self::Engajamento => 'Engajamento',
            self::Atendimento => 'Atendimento',
            self::Comercial => 'Comercial',
            self::Indicacao => 'Indicação',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            self::cases(),
        );
    }
}
