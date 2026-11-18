<?php

declare(strict_types=1);

namespace App\Services\Feedback;

use PhpOffice\PhpWord\Element\AbstractElement;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\TextBreak;
use PhpOffice\PhpWord\IOFactory;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

/**
 * Lê a transcrição exportada do Plaud (TXT, SRT, DOCX ou PDF) e devolve o texto original, sem limpar nem
 * resumir: só decodifica, junta os blocos do SRT com a marcação de tempo e normaliza quebras de linha.
 * Não grava nada em disco; quem chama descarta o arquivo depois.
 */
class PlaudTranscriptExtractor
{
    public const MAX_TEXT_CHARS = 1_000_000;

    private const PDF_TIMEOUT_SECONDS = 120;

    /**
     * @return array{format: string, text: string, has_speakers: bool, has_timestamps: bool}
     *
     * @throws TranscriptExtractionException
     */
    public function extract(string $path, string $clientFilename): array
    {
        $format = strtolower(pathinfo($clientFilename, PATHINFO_EXTENSION));

        if (! is_file($path) || filesize($path) === 0) {
            throw new TranscriptExtractionException('O arquivo está vazio.');
        }

        $text = match ($format) {
            'txt' => $this->decodeText((string) file_get_contents($path)),
            'srt' => $this->parseSrt($this->decodeText((string) file_get_contents($path))),
            'docx' => $this->readDocx($path),
            'pdf' => $this->readPdf($path),
            default => throw new TranscriptExtractionException('Formato não aceito. Envie TXT, SRT, DOCX ou PDF.'),
        };

        $text = $this->normalize($text);

        if ($text === '') {
            throw new TranscriptExtractionException('Não encontramos texto no arquivo. Confira se é a transcrição exportada do Plaud.');
        }

        if (mb_strlen($text) > self::MAX_TEXT_CHARS) {
            throw new TranscriptExtractionException('A transcrição passa do limite de 1.000.000 caracteres. Divida a gravação e envie de novo.');
        }

        return [
            'format' => $format,
            'text' => $text,
            'has_speakers' => $this->hasSpeakerLabels($text),
            'has_timestamps' => $this->hasTimestamps($text),
        ];
    }

    /**
     * Identificação de quem fala: rótulos genéricos do Plaud («Speaker 1», «Falante 2»…) em pelo menos duas
     * linhas, ou nomes no início da linha seguidos de «:» ou de marcação de tempo (dois nomes, três linhas).
     */
    public function hasSpeakerLabels(string $text): bool
    {
        $time = '\[?\d{1,2}:\d{2}(?::\d{2})?(?:[.,]\d{1,3})?\]?';

        $generic = preg_match_all(
            '/^(?:'.$time.'\s*)?(?:speaker|falante|orador|locutor|participante|interlocutor)\s*\d+\b/imu',
            $text,
        );
        if ($generic >= 2) {
            return true;
        }

        preg_match_all(
            '/^(?:'.$time.'\s*)?(\p{Lu}[\p{L}\'.-]*(?:\s+\p{Lu}[\p{L}\'.-]*){0,3})\s*(?::\s|\s+'.$time.'\s*$)/mu',
            $text,
            $matches,
        );
        $labels = array_map(fn (string $label) => mb_strtolower($label), $matches[1]);

        return count($labels) >= 3 && count(array_unique($labels)) >= 2;
    }

    /** Marcação de tempo (hh:mm:ss, ou mm:ss no início da linha) em pelo menos duas linhas. */
    public function hasTimestamps(string $text): bool
    {
        return preg_match_all('/^.*?(?:\b\d{1,2}:\d{2}:\d{2}\b|^\s*\[?\d{1,2}:\d{2}\]?(?=\s|$)).*$/mu', $text) >= 2;
    }

    /**
     * UTF-8 (com ou sem BOM), UTF-16 com BOM ou, se não for UTF-8 válido, Windows-1252.
     */
    private function decodeText(string $bytes): string
    {
        if (str_starts_with($bytes, "\xEF\xBB\xBF")) {
            $bytes = substr($bytes, 3);
        } elseif (str_starts_with($bytes, "\xFF\xFE")) {
            $bytes = mb_convert_encoding(substr($bytes, 2), 'UTF-8', 'UTF-16LE');
        } elseif (str_starts_with($bytes, "\xFE\xFF")) {
            $bytes = mb_convert_encoding(substr($bytes, 2), 'UTF-8', 'UTF-16BE');
        }

        if (str_contains($bytes, "\0")) {
            throw new TranscriptExtractionException('O arquivo não parece ser texto. Envie a transcrição exportada do Plaud.');
        }

        if (! mb_check_encoding($bytes, 'UTF-8')) {
            $bytes = mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252');
        }

        return $bytes;
    }

    /**
     * Cada bloco do SRT vira uma linha «[hh:mm:ss] texto» (o texto mantém o falante, se o Plaud o incluiu).
     */
    private function parseSrt(string $content): string
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $blocks = preg_split('/\n[ \t]*\n/', trim($content)) ?: [];
        $lines = [];
        $timed = 0;

        foreach ($blocks as $block) {
            $rows = array_values(array_filter(array_map('trim', explode("\n", $block)), fn ($r) => $r !== ''));
            $start = null;
            $textRows = [];

            foreach ($rows as $i => $row) {
                if ($start === null && preg_match('/^(\d{1,2}):(\d{2}):(\d{2})[,.]\d{1,3}\s*-->/', $row, $m)) {
                    $start = sprintf('%02d:%s:%s', (int) $m[1], $m[2], $m[3]);

                    continue;
                }
                if ($start === null && $i === 0 && ctype_digit($row)) {
                    continue; // número do bloco
                }
                $textRows[] = $row;
            }

            if ($start === null) {
                continue;
            }

            $timed++;
            $text = implode(' ', $textRows);
            if ($text !== '') {
                $lines[] = '['.$start.'] '.$text;
            }
        }

        if ($timed === 0) {
            throw new TranscriptExtractionException('Não encontramos marcações de tempo no SRT. Confira se é o arquivo exportado pelo Plaud.');
        }

        return implode("\n", $lines);
    }

    private function readDocx(string $path): string
    {
        $zip = new ZipArchive;
        $isWord = false;
        if ($zip->open($path) === true) {
            $isWord = $zip->locateName('word/document.xml') !== false;
            $zip->close();
        }

        if (! $isWord) {
            throw new TranscriptExtractionException('O arquivo DOCX está corrompido ou não é um documento do Word.');
        }

        try {
            $document = IOFactory::createReader('Word2007')->load($path);
        } catch (Throwable) {
            throw new TranscriptExtractionException('O arquivo DOCX está corrompido ou não é um documento do Word.');
        }

        $lines = [];
        foreach ($document->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $lines[] = $this->docxElementText($element);
            }
        }

        return implode("\n", $lines);
    }

    private function docxElementText(AbstractElement $element): string
    {
        if ($element instanceof TextBreak) {
            return "\n";
        }

        if ($element instanceof Table) {
            $rows = [];
            foreach ($element->getRows() as $row) {
                $cells = [];
                foreach ($row->getCells() as $cell) {
                    $cells[] = trim(implode(' ', array_map(fn ($child) => $this->docxElementText($child), $cell->getElements())));
                }
                $rows[] = implode(' | ', $cells);
            }

            return implode("\n", $rows);
        }

        if (method_exists($element, 'getElements')) {
            return implode('', array_map(fn ($child) => $this->docxElementText($child), $element->getElements()));
        }

        if (method_exists($element, 'getText')) {
            $text = $element->getText();
            if (is_string($text)) {
                return $text;
            }
            if ($text instanceof AbstractElement) {
                return $this->docxElementText($text);
            }
        }

        return '';
    }

    /**
     * PDF pela extração já usada no container (Python + pdfplumber).
     */
    private function readPdf(string $path): string
    {
        $handle = fopen($path, 'rb');
        $header = $handle ? (string) fread($handle, 5) : '';
        if ($handle) {
            fclose($handle);
        }

        if ($header !== '%PDF-') {
            throw new TranscriptExtractionException('O arquivo PDF está corrompido ou não é um PDF.');
        }

        $script = <<<'PY'
import sys, pdfplumber
with pdfplumber.open(sys.argv[1]) as pdf:
    sys.stdout.write("\n\n".join((page.extract_text() or "") for page in pdf.pages))
PY;

        $python = trim((string) config('rhid.espelho_python')) ?: 'python3';
        $process = new Process([$python, '-c', $script, $path], null, ['PYTHONIOENCODING' => 'utf-8'], null, self::PDF_TIMEOUT_SECONDS);

        try {
            $process->run();
        } catch (Throwable) {
            throw new TranscriptExtractionException('Não foi possível ler o PDF. Exporte a transcrição do Plaud em TXT, SRT ou DOCX.');
        }

        if (! $process->isSuccessful()) {
            report(new \RuntimeException('Leitura de PDF da transcrição falhou: '.mb_substr($process->getErrorOutput(), 0, 500)));

            throw new TranscriptExtractionException('Não foi possível ler o PDF. Exporte a transcrição do Plaud em TXT, SRT ou DOCX.');
        }

        return $process->getOutput();
    }

    private function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text);
        $text = preg_replace('/[ \t]+$/m', '', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
