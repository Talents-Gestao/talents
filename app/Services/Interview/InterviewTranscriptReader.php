<?php

declare(strict_types=1);

namespace App\Services\Interview;

use App\Enums\InterviewTranscriptSource;
use App\Services\Feedback\PlaudTranscriptExtractor;
use App\Services\Feedback\TranscriptExtractionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Texto da entrevista a partir das fontes de texto (nunca chama o Whisper):
 * - manual_text e plaud colado: o texto como veio, só com quebras de linha normalizadas;
 * - file_upload: TXT (UTF-8, UTF-16 com BOM ou Windows-1252) ou VTT, convertido em texto sem marcações de tempo;
 * - plaud com arquivo: TXT exportado do Plaud, lido pelo mesmo parser dos Feedbacks (PlaudTranscriptExtractor).
 */
final class InterviewTranscriptReader
{
    public const MAX_FILE_KB = 2048;

    public const MAX_TEXT_CHARS = 1_000_000;

    public function __construct(
        private readonly PlaudTranscriptExtractor $plaud,
    ) {}

    public function fromText(string $text): string
    {
        return $this->ensureUsable($this->normalize($text));
    }

    public function fromFile(UploadedFile $file, InterviewTranscriptSource $source): string
    {
        $extension = mb_strtolower($file->getClientOriginalExtension());
        $path = (string) $file->getRealPath();

        if ($source === InterviewTranscriptSource::Plaud) {
            if ($extension !== 'txt') {
                $this->fail('Envie o TXT exportado do Plaud ou cole o texto.');
            }
            try {
                return $this->ensureUsable($this->plaud->extract($path, $file->getClientOriginalName())['text']);
            } catch (TranscriptExtractionException $e) {
                $this->fail($e->getMessage());
            }
        }

        $bytes = (string) file_get_contents($path);

        $text = match ($extension) {
            'txt' => $this->decode($bytes),
            'vtt' => $this->vttToText($this->decode($bytes)),
            default => $this->fail('Envie um arquivo TXT ou VTT.'),
        };

        return $this->ensureUsable($this->normalize($text));
    }

    /**
     * WebVTT → texto: remove o cabeçalho, blocos NOTE/STYLE/REGION, identificadores e linhas de tempo;
     * a voz «<v Nome>» vira «Nome: » e as demais marcações são retiradas.
     */
    public function vttToText(string $content): string
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $blocks = preg_split('/\n[ \t]*\n/', trim($content)) ?: [];
        $lines = [];

        foreach ($blocks as $block) {
            $rows = array_values(array_filter(array_map('trim', explode("\n", $block)), fn ($r) => $r !== ''));
            if ($rows === []) {
                continue;
            }
            if (preg_match('/^(WEBVTT|NOTE|STYLE|REGION)\b/', $rows[0]) === 1) {
                continue;
            }

            $timingIndex = null;
            foreach ($rows as $i => $row) {
                if (str_contains($row, '-->')) {
                    $timingIndex = $i;
                    break;
                }
            }
            if ($timingIndex === null) {
                continue;
            }

            foreach (array_slice($rows, $timingIndex + 1) as $row) {
                $row = preg_replace('/<v(?:\.[^\s>]+)?\s+([^>]+)>/u', '$1: ', $row) ?? $row;
                $row = preg_replace('/<\/?[^>]+>/u', '', $row) ?? $row;
                $row = html_entity_decode($row, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $row = trim(preg_replace('/\s+/u', ' ', $row) ?? $row);
                if ($row !== '') {
                    $lines[] = $row;
                }
            }
        }

        if ($lines === []) {
            $this->fail('Não encontramos falas no arquivo VTT.');
        }

        return implode("\n", $lines);
    }

    private function decode(string $bytes): string
    {
        if (str_starts_with($bytes, "\xEF\xBB\xBF")) {
            $bytes = substr($bytes, 3);
        } elseif (str_starts_with($bytes, "\xFF\xFE")) {
            $bytes = mb_convert_encoding(substr($bytes, 2), 'UTF-8', 'UTF-16LE');
        } elseif (str_starts_with($bytes, "\xFE\xFF")) {
            $bytes = mb_convert_encoding(substr($bytes, 2), 'UTF-8', 'UTF-16BE');
        }

        if (str_contains($bytes, "\0")) {
            $this->fail('O arquivo não parece ser texto.');
        }

        if (! mb_check_encoding($bytes, 'UTF-8')) {
            $bytes = mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252');
        }

        return $bytes;
    }

    private function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text);
        $text = preg_replace('/[ \t]+$/m', '', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function ensureUsable(string $text): string
    {
        if ($text === '') {
            $this->fail('O texto da entrevista está vazio.');
        }
        if (mb_strlen($text) > self::MAX_TEXT_CHARS) {
            $this->fail('A transcrição passa do limite de 1.000.000 caracteres.');
        }

        return $text;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['transcript' => $message]);
    }
}
