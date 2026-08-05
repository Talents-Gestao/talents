<?php

declare(strict_types=1);

namespace App\Enums;

enum LandingInterestSource: string
{
    case Site = 'site';
    case Phone = 'phone';
    case WhatsApp = 'whatsapp';
    case Referral = 'referral';
    case Event = 'event';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Site => 'Site',
            self::Phone => 'Telefone',
            self::WhatsApp => 'WhatsApp',
            self::Referral => 'Indicação',
            self::Event => 'Evento',
            self::Other => 'Outro',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
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
