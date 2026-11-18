<?php

declare(strict_types=1);

namespace App\Support\Feedback;

use App\Models\FeedbackRecording;

/**
 * Rótulos de quem fala na transcrição do Plaud e troca dos rótulos pelos papéis confirmados pelo usuário.
 * Formatos aceitos (os mesmos da leitura na fatia 1): «Speaker 1: texto», «[00:00:01] Speaker 1: texto»,
 * «00:00:01 Speaker 1» (fala na linha seguinte), «Speaker 1 00:00:01» e nomes no lugar de «Speaker N».
 */
final class TranscriptSpeakers
{
    public const MAX_LABELS = 8;

    public const ROLE_NAMES = [
        FeedbackRecording::ROLE_LEADER => 'Líder',
        FeedbackRecording::ROLE_EMPLOYEE => 'Colaborador',
        FeedbackRecording::ROLE_OTHER => 'Outra pessoa',
    ];

    private const TIME = '\[?\d{1,2}:\d{2}(?::\d{2})?(?:[.,]\d{1,3})?\]?';

    private const GENERIC = '(?i:speaker|falante|orador|locutor|participante|interlocutor)\s*\d+';

    private const NAME = '\p{Lu}[\p{L}\'.-]*(?:\s+\p{Lu}[\p{L}\'.-]*){0,3}';

    /**
     * Rótulos na ordem em que aparecem, com a primeira fala de cada um (para o usuário reconhecer quem é).
     * Nomes só contam com duas falas ou mais (evita «Obs:» e afins).
     *
     * @return list<array{label: string, sample: string, count: int}>
     */
    public static function labels(string $text): array
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        $found = [];

        foreach ($lines as $i => $line) {
            $parsed = self::parseLine($line);
            if ($parsed === null) {
                continue;
            }

            [, $label, $speech, $generic] = $parsed;
            if ($speech === '') {
                $speech = self::nextSpeech($lines, $i);
            }

            $key = self::key($label);
            if (! isset($found[$key])) {
                $found[$key] = ['label' => self::display($label), 'sample' => self::sample($speech), 'count' => 0, 'generic' => $generic];
            } elseif ($found[$key]['sample'] === '' && $speech !== '') {
                $found[$key]['sample'] = self::sample($speech);
            }
            $found[$key]['count']++;
        }

        $result = [];
        foreach ($found as $item) {
            if (! $item['generic'] && $item['count'] < 2) {
                continue;
            }
            $result[] = ['label' => $item['label'], 'sample' => $item['sample'], 'count' => $item['count']];
        }

        return array_slice($result, 0, self::MAX_LABELS);
    }

    /**
     * Troca cada rótulo mapeado pelo papel («Líder», «Colaborador», «Outra pessoa 1»…). Rótulos sem mapa ficam como estão.
     *
     * @param  array<string, string>  $map  rótulo => papel
     */
    public static function applyRoles(string $text, array $map): string
    {
        $names = self::roleNames($map);
        $lines = preg_split('/\R/u', $text) ?: [];

        foreach ($lines as $i => $line) {
            $parsed = self::parseLine($line);
            if ($parsed === null) {
                continue;
            }

            [$pattern, $label] = $parsed;
            $name = $names[self::key($label)] ?? null;
            if ($name === null) {
                continue;
            }

            $lines[$i] = (string) preg_replace_callback(
                $pattern,
                fn (array $m) => $m['pre'].$name.$m['post'].($m['speech'] ?? ''),
                $line,
                1,
            );
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, string>  $map
     * @return array<string, string> chave normalizada do rótulo => nome do papel
     */
    public static function roleNames(array $map): array
    {
        $byRole = [];
        foreach ($map as $label => $role) {
            $byRole[$role][] = self::key((string) $label);
        }

        $names = [];
        foreach ($byRole as $role => $keys) {
            $base = self::ROLE_NAMES[$role] ?? self::ROLE_NAMES[FeedbackRecording::ROLE_OTHER];
            // Líder e colaborador podem ter mais de um rótulo (o Plaud às vezes divide a mesma pessoa): mesmo nome.
            $numbered = $role === FeedbackRecording::ROLE_OTHER && count($keys) > 1;
            foreach (array_values($keys) as $n => $key) {
                $names[$key] = $numbered ? $base.' '.($n + 1) : $base;
            }
        }

        return $names;
    }

    public static function key(string $label): string
    {
        return mb_strtolower(self::display($label));
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: bool}|null [padrão, rótulo, fala na mesma linha, genérico]
     */
    private static function parseLine(string $line): ?array
    {
        $label = '(?<label>'.self::GENERIC.'|'.self::NAME.')';
        $patterns = [
            // «[00:00:01] Speaker 1: texto» / «Speaker 1: texto»
            '/^(?<pre>\s*(?:'.self::TIME.'\s*)?)'.$label.'(?<post>\s*:\s*)(?<speech>.*)$/u',
            // «00:00:01 Speaker 1»
            '/^(?<pre>\s*'.self::TIME.'\s+)'.$label.'(?<post>\s*)$/u',
            // «Speaker 1 00:00:01»
            '/^(?<pre>\s*)'.$label.'(?<post>\s+'.self::TIME.'\s*)$/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $line, $m)) {
                $generic = (bool) preg_match('/^'.self::GENERIC.'$/iu', $m['label']);
                // Nome comum seguido de «:» só conta se for curto (evita frases como «Combinamos o seguinte:»).
                if (! $generic && mb_strlen($m['label']) > 40) {
                    continue;
                }

                return [$pattern, $m['label'], trim($m['speech'] ?? ''), $generic];
            }
        }

        // Rótulo genérico sem «:» (ex.: «Speaker 1 texto»), só para rótulos genéricos.
        $generic = '/^(?<pre>\s*(?:'.self::TIME.'\s*)?)(?<label>'.self::GENERIC.')(?<post>\s+)(?<speech>\S.*)$/iu';
        if (preg_match($generic, $line, $m)) {
            return [$generic, $m['label'], trim($m['speech']), true];
        }

        return null;
    }

    /** @param list<string> $lines */
    private static function nextSpeech(array $lines, int $index): string
    {
        for ($j = $index + 1, $max = min(count($lines), $index + 4); $j < $max; $j++) {
            $candidate = trim($lines[$j]);
            if ($candidate === '') {
                continue;
            }

            return self::parseLine($lines[$j]) === null ? $candidate : '';
        }

        return '';
    }

    private static function display(string $label): string
    {
        $label = trim((string) preg_replace('/\s+/u', ' ', $label));

        return preg_match('/^'.self::GENERIC.'$/iu', $label) ? mb_convert_case($label, MB_CASE_TITLE) : $label;
    }

    private static function sample(string $speech): string
    {
        $speech = trim($speech);

        return mb_strlen($speech) > 120 ? rtrim(mb_substr($speech, 0, 117)).'…' : $speech;
    }
}
