<?php

declare(strict_types=1);

namespace App\Enums;

/** Decisão humana da avaliação da candidatura (a IA não decide). */
enum HiringAssessmentDecision: string
{
    case Pending = 'pending';
    case Recommended = 'recommended';
    case NotRecommended = 'not_recommended';
    case OnHold = 'on_hold';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Recommended => 'Recomendado',
            self::NotRecommended => 'Não recomendado',
            self::OnHold => 'Em espera',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(static fn (self $d) => ['value' => $d->value, 'label' => $d->label()], self::cases());
    }
}
