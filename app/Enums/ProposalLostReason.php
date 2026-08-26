<?php

declare(strict_types=1);

namespace App\Enums;

enum ProposalLostReason: string
{
    case Preco = 'preco';
    case Timing = 'timing';
    case Concorrencia = 'concorrencia';
    case SemRetorno = 'sem_retorno';
    case Escopo = 'escopo';
    case Outros = 'outros';

    public function label(): string
    {
        return match ($this) {
            self::Preco => 'Preço',
            self::Timing => 'Timing',
            self::Concorrencia => 'Concorrência',
            self::SemRetorno => 'Sem retorno',
            self::Escopo => 'Escopo',
            self::Outros => 'Outros',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            self::cases(),
        );
    }
}
