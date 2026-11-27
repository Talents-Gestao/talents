<?php

declare(strict_types=1);

namespace App\Enums;

enum InterviewAnswerOrigin: string
{
    case AiSuggestion = 'ai_suggestion';
    case Human = 'human';

    public function label(): string
    {
        return match ($this) {
            self::AiSuggestion => 'Sugestão da IA',
            self::Human => 'Revisão humana',
        };
    }
}
