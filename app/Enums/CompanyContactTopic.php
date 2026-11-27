<?php

declare(strict_types=1);

namespace App\Enums;

/** Assuntos que um contato da empresa aceita receber. */
enum CompanyContactTopic: string
{
    case Vagas = 'vagas';
    case PosContratacao = 'pos_contratacao';
    case Pesquisas = 'pesquisas';

    public function label(): string
    {
        return match ($this) {
            self::Vagas => 'Vagas',
            self::PosContratacao => 'Pós-contratação',
            self::Pesquisas => 'Pesquisas',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(static fn (self $t) => ['value' => $t->value, 'label' => $t->label()], self::cases());
    }
}
