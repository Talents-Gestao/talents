<?php

declare(strict_types=1);

namespace App\Enums;

enum ClientEvaluationStatus: string
{
    case Pending = 'pending';
    case Answered = 'answered';
    case Expired = 'expired';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Answered => 'Respondida',
            self::Expired => 'Expirada',
            self::Superseded => 'Substituída',
        };
    }

    public function unavailableMessage(): string
    {
        return match ($this) {
            self::Answered => 'Esta avaliação já foi enviada. Obrigado.',
            self::Expired => 'O prazo desta avaliação encerrou.',
            self::Superseded => 'Este link foi substituído. Peça uma nova avaliação à Talents.',
            self::Pending => 'Esta avaliação não está disponível.',
        };
    }
}
