<?php

declare(strict_types=1);

namespace App\Support\Feedback;

/**
 * Divide a transcrição em blocos para a limpeza por IA, sempre em quebra de linha. Cada bloco leva junto o fim
 * do bloco anterior como contexto (sobreposição): a IA usa esse trecho só para entender a conversa e limpa
 * apenas o corpo do bloco, de modo que cada linha é limpa exatamente uma vez e a ordem é preservada.
 */
final class TranscriptChunker
{
    public const MAX_CHARS = 6000;

    public const OVERLAP_CHARS = 600;

    /**
     * @return list<array{context: string, body: string}>
     */
    public static function chunks(string $text, int $maxChars = self::MAX_CHARS, int $overlapChars = self::OVERLAP_CHARS): array
    {
        $lines = [];
        foreach (preg_split('/\R/u', trim($text)) ?: [] as $line) {
            foreach (self::splitLongLine($line, $maxChars) as $piece) {
                $lines[] = $piece;
            }
        }

        $chunks = [];
        $current = [];
        $length = 0;

        foreach ($lines as $line) {
            $lineLength = mb_strlen($line) + 1;
            if ($current !== [] && $length + $lineLength > $maxChars) {
                // Cabeçalho de fala («00:12:34 Líder») não fica no fim do bloco, longe da fala: vai para o próximo.
                $carry = [];
                while ($current !== [] && (trim((string) end($current)) === '' || self::isHeader((string) end($current)))) {
                    $isHeader = self::isHeader((string) end($current));
                    array_unshift($carry, array_pop($current));
                    if ($isHeader) {
                        break;
                    }
                }
                if ($current === [] || ! self::isHeader((string) ($carry[0] ?? ''))) {
                    $current = array_merge($current, $carry); // nada a carregar (ou bloco só de cabeçalho)
                    $carry = [];
                }

                $chunks[] = $current;
                $current = array_values(array_filter($carry, fn ($l) => trim($l) !== ''));
                $length = array_sum(array_map(fn ($l) => mb_strlen($l) + 1, $current));
            }
            $current[] = $line;
            $length += $lineLength;
        }

        if ($current !== [] && trim(implode('', $current)) !== '') {
            $chunks[] = $current;
        }

        $result = [];
        foreach ($chunks as $i => $bodyLines) {
            $result[] = [
                'context' => $i === 0 ? '' : self::tail($chunks[$i - 1], $overlapChars),
                'body' => trim(implode("\n", $bodyLines)),
            ];
        }

        return $result;
    }

    /**
     * Linha só com quem fala e/ou a marcação de tempo, sem a fala (formato do Plaud: a fala vem na linha seguinte).
     */
    public static function isHeader(string $line): bool
    {
        $time = '\[?\d{1,2}:\d{2}(?::\d{2})?(?:[.,]\d{1,3})?\]?';
        $label = '[^\s.,;:!?][^.,;:!?]{0,39}';

        return (bool) preg_match('/^\s*(?:'.$time.'(?:\s+'.$label.')?|'.$label.'\s+'.$time.')\s*$/u', $line);
    }

    /** @param list<string> $lines */
    private static function tail(array $lines, int $overlapChars): string
    {
        $picked = [];
        $length = 0;

        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $picked[] = $lines[$i];
            $length += mb_strlen($lines[$i]) + 1;
            if ($length >= $overlapChars) {
                break;
            }
        }

        $tail = trim(implode("\n", array_reverse($picked)));

        return mb_strlen($tail) > $overlapChars * 2 ? mb_substr($tail, -$overlapChars * 2) : $tail;
    }

    /** Linha maior que o bloco (texto sem quebras): corta em fim de frase ou, em último caso, em espaço. */
    private static function splitLongLine(string $line, int $maxChars): array
    {
        if (mb_strlen($line) <= $maxChars) {
            return [$line];
        }

        $pieces = [];
        $rest = $line;
        while (mb_strlen($rest) > $maxChars) {
            $window = mb_substr($rest, 0, $maxChars);
            $cut = max((int) mb_strrpos($window, '. '), (int) mb_strrpos($window, '? '), (int) mb_strrpos($window, '! '));
            if ($cut < $maxChars / 2) {
                $cut = (int) mb_strrpos($window, ' ');
            }
            if ($cut <= 0) {
                $cut = $maxChars - 1;
            }
            $pieces[] = trim(mb_substr($rest, 0, $cut + 1));
            $rest = ltrim(mb_substr($rest, $cut + 1));
        }
        if ($rest !== '') {
            $pieces[] = $rest;
        }

        return $pieces;
    }
}
