<?php

declare(strict_types=1);

namespace App\Enums;

enum InterviewTranscriptSource: string
{
    case AudioWhisper = 'audio_whisper';
    case ManualText = 'manual_text';
    case FileUpload = 'file_upload';
    case Plaud = 'plaud';

    public function label(): string
    {
        return match ($this) {
            self::AudioWhisper => 'Áudio (transcrição pelo Whisper)',
            self::ManualText => 'Texto colado',
            self::FileUpload => 'Arquivo TXT ou VTT',
            self::Plaud => 'Plaud (texto colado ou TXT)',
        };
    }

    /** Fontes de texto pulam a transcrição. */
    public function isText(): bool
    {
        return $this !== self::AudioWhisper;
    }

    /**
     * @return list<array{value: string, label: string, is_text: bool}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $s) => ['value' => $s->value, 'label' => $s->label(), 'is_text' => $s->isText()],
            self::cases(),
        );
    }
}
