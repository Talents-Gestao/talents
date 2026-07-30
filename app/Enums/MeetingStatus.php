<?php

declare(strict_types=1);

namespace App\Enums;

enum MeetingStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Transcribing = 'transcribing';
    case GeneratingMinutes = 'generating_minutes';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Queued => 'Na fila',
            self::Transcribing => 'Transcrevendo áudio',
            self::GeneratingMinutes => 'Gerando ata',
            self::Completed => 'Concluída',
            self::Failed => 'Falhou',
        };
    }

    public function isProcessing(): bool
    {
        return in_array($this, [
            self::Queued,
            self::Transcribing,
            self::GeneratingMinutes,
        ], true);
    }

    public function canReceiveAudio(): bool
    {
        return in_array($this, [
            self::Draft,
            self::Failed,
            self::Completed,
        ], true);
    }
}
