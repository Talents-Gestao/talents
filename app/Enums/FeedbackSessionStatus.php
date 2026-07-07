<?php

declare(strict_types=1);

namespace App\Enums;

enum FeedbackSessionStatus: string
{
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case AwaitingSignatures = 'awaiting_signatures';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::InProgress => 'Em preenchimento',
            self::AwaitingSignatures => 'Aguardando assinaturas',
            self::Completed => 'Concluído',
            self::Cancelled => 'Cancelado',
        };
    }
}
