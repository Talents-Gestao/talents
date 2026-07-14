<?php

declare(strict_types=1);

namespace App\Enums;

enum NewsCategory: string
{
    case Hr = 'hr';
    case Entrepreneurship = 'entrepreneurship';
    case Accounting = 'accounting';
    case Health = 'health';
    case Trends = 'trends';
    case Launches = 'launches';
    case ImportantDates = 'important_dates';
    case Events = 'events';

    public function label(): string
    {
        return match ($this) {
            self::Hr => 'RH e Gestão de Pessoas',
            self::Entrepreneurship => 'Empreendedorismo e Negócios',
            self::Accounting => 'Contabilidade e Legislação',
            self::Health => 'Saúde e Bem-estar',
            self::Trends => 'Tendências e oportunidades',
            self::Launches => 'Novidades e lançamentos',
            self::ImportantDates => 'Datas importantes próximas',
            self::Events => 'Eventos relevantes',
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::Hr => '👥',
            self::Entrepreneurship => '🚀',
            self::Accounting => '⚖️',
            self::Health => '🌿',
            self::Trends => '📈',
            self::Launches => '💡',
            self::ImportantDates => '📅',
            self::Events => '🎟️',
        };
    }

    /**
     * Consulta usada no RSS do Google News (pt-BR).
     */
    public function searchQuery(): string
    {
        return match ($this) {
            self::Hr => 'recursos humanos OR "gestão de pessoas" OR RH',
            self::Entrepreneurship => 'empreendedorismo OR startups OR negócios',
            self::Accounting => 'contabilidade OR "legislação trabalhista" OR eSocial',
            self::Health => '"saúde no trabalho" OR "bem-estar" OR "qualidade de vida"',
            self::Trends => '"futuro do trabalho" OR "tendências de mercado" OR inovação',
            self::Launches => 'lançamentos OR "novidade empresarial" OR tecnologia negócios',
            self::ImportantDates => '"calendário fiscal" OR feriados OR "datas importantes"',
            self::Events => 'eventos OR congresso OR "feira de negócios" OR RH',
        };
    }

    /**
     * @return list<array{value: string, label: string, emoji: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $category): array => [
                'value' => $category->value,
                'label' => $category->label(),
                'emoji' => $category->emoji(),
            ],
            self::cases(),
        );
    }
}
