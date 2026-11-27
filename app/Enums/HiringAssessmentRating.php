<?php

declare(strict_types=1);

namespace App\Enums;

/** Avaliação de um critério da candidatura, sempre apoiada em evidência. */
enum HiringAssessmentRating: string
{
    case Atende = 'atende';
    case Parcial = 'parcial';
    case NaoAtende = 'nao_atende';
    case SemEvidencia = 'sem_evidencia';

    public function label(): string
    {
        return match ($this) {
            self::Atende => 'Atende',
            self::Parcial => 'Atende parcialmente',
            self::NaoAtende => 'Não atende',
            self::SemEvidencia => 'Sem evidência',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(static fn (self $r) => ['value' => $r->value, 'label' => $r->label()], self::cases());
    }
}
