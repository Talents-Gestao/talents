<?php

declare(strict_types=1);

namespace App\Support\Feedback;

use App\Models\FeedbackRecording;
use Illuminate\Support\Str;

/**
 * Sugestão de papel de cada falante, sem IA, para a etapa «Confirmar falantes». Combina:
 * - quem fala primeiro costuma ser o líder (regra que já existia);
 * - quem cita o nome de uma pessoa da sessão não é essa pessoa («Bom dia, Bruno» vem de quem não é o Bruno);
 * - rótulo com o nome da pessoa (o Plaud às vezes usa nomes no lugar de «Speaker N»).
 * Confiança alta só com exatamente 2 falantes, alguma evidência de nome e nenhuma evidência contrária.
 * É só sugestão: o usuário sempre confirma, e o servidor valida o mapa como antes.
 */
final class SpeakerRoleSuggestion
{
    public const HIGH = 'high';

    public const LOW = 'low';

    /**
     * @param  list<array{label: string, utterances: list<string>}>  $speakers  na ordem em que aparecem
     * @return array{map: array<string, string>, confidence: string, reason: ?string}
     */
    public static function suggest(array $speakers, ?string $leaderName, ?string $employeeName): array
    {
        if (count($speakers) < 2) {
            return ['map' => array_fill_keys(array_column($speakers, 'label'), FeedbackRecording::ROLE_OTHER), 'confidence' => self::LOW, 'reason' => null];
        }

        $leader = self::person($leaderName);
        $employee = self::person($employeeName);

        // Os dois que mais falam disputam líder e colaborador; os demais são «outra pessoa».
        $ranked = $speakers;
        usort($ranked, fn ($a, $b) => count($b['utterances']) <=> count($a['utterances']));
        $pair = array_slice($ranked, 0, 2);
        // Mantém a ordem de aparição entre os dois (para a regra «quem fala primeiro»).
        $pairLabels = array_column($pair, 'label');
        $first = array_values(array_filter($speakers, fn ($s) => in_array($s['label'], $pairLabels, true)));
        [$a, $b] = [$first[0], $first[1]];

        $asLeader = fn (array $s) => self::mentions($s, $employee) + self::LABEL_WEIGHT * (int) self::labelIs($s, $leader);
        $asEmployee = fn (array $s) => self::mentions($s, $leader) + self::LABEL_WEIGHT * (int) self::labelIs($s, $employee);

        $straight = $asLeader($a) + $asEmployee($b);
        $swapped = $asLeader($b) + $asEmployee($a);
        $swap = $swapped > $straight;
        $winner = max($straight, $swapped);
        $loser = min($straight, $swapped);

        [$leaderSpeaker, $employeeSpeaker] = $swap ? [$b, $a] : [$a, $b];

        $map = [];
        foreach ($speakers as $s) {
            $map[$s['label']] = match ($s['label']) {
                $leaderSpeaker['label'] => FeedbackRecording::ROLE_LEADER,
                $employeeSpeaker['label'] => FeedbackRecording::ROLE_EMPLOYEE,
                default => FeedbackRecording::ROLE_OTHER,
            };
        }

        // Alta: só 2 falantes, evidência de vocativo (ou rótulo) e vantagem clara sobre a leitura invertida.
        // Uma citação solta do próprio nome em terceira pessoa («o Bruno merece…») não derruba a confiança.
        $confidence = count($speakers) === 2 && $winner >= self::VOCATIVE_WEIGHT && $winner > $loser && $winner >= 3 * $loser
            ? self::HIGH
            : self::LOW;

        $reasons = [];
        if ($employee !== null && self::mentions($leaderSpeaker, $employee) > 0) {
            $reasons[] = "{$leaderSpeaker['label']} chama {$employee['display']} pelo nome";
        }
        if ($leader !== null && self::mentions($employeeSpeaker, $leader) > 0) {
            $reasons[] = "{$employeeSpeaker['label']} chama {$leader['display']} pelo nome";
        }
        if ($reasons === [] && $winner === 0) {
            $reasons[] = "{$leaderSpeaker['label']} começa a conversa";
        }

        return ['map' => $map, 'confidence' => $confidence, 'reason' => $reasons === [] ? null : ucfirst(implode(' e ', $reasons)).'.'];
    }

    /** Peso de uma fala que chama a pessoa pelo nome («Bom dia, Bruno», «obrigado, Bruno»). */
    public const VOCATIVE_WEIGHT = 3;

    /** Peso de uma citação em terceira pessoa («o Bruno», «do Bruno»). */
    public const MENTION_WEIGHT = 1;

    /** Rótulo com o nome da pessoa (o Plaud às vezes usa nomes no lugar de «Speaker N»). */
    public const LABEL_WEIGHT = 6;

    private const GREETING = '(?:bom dia|boa tarde|boa noite|oi|ola|e ai|obrigad[oa]|valeu|tchau|parabens|ate logo|ate mais|ate segunda|com licenca)';

    /** Primeiro nome comparável (sem texto entre parênteses, acentos e caixa); null se curto ou vazio. */
    public static function firstName(?string $name): ?string
    {
        $normalized = FeedbackEmployeeDuplicateFinder::normalizeName($name);
        $first = explode(' ', $normalized)[0] ?? '';

        return strlen($first) >= 3 ? $first : null;
    }

    /**
     * Pessoa da sessão, normalizada: primeiro nome, nome completo e nome para exibir (sem o texto entre parênteses).
     *
     * @return array{first: string, full: string, display: string}|null
     */
    public static function person(?string $name): ?array
    {
        $first = self::firstName($name);
        if ($first === null) {
            return null;
        }

        return [
            'first' => $first,
            'full' => FeedbackEmployeeDuplicateFinder::normalizeName($name),
            'display' => trim((string) preg_replace('/\s*\([^)]*\)\s*/u', ' ', (string) $name)),
        ];
    }

    /**
     * Pontos das falas que citam a pessoa (pelo nome completo ou pelo primeiro nome, palavra inteira): vocativo vale
     * VOCATIVE_WEIGHT, citação em terceira pessoa vale MENTION_WEIGHT. Cada fala conta uma vez.
     *
     * @param  array{first: string, full: string}|null  $person
     */
    private static function mentions(array $speaker, ?array $person): int
    {
        if ($person === null) {
            return 0;
        }

        $names = array_unique(array_filter([$person['full'], $person['first']]));
        $score = 0;
        foreach ($speaker['utterances'] as $u) {
            $text = ' '.trim((string) preg_replace('/\s+/', ' ', Str::lower(Str::ascii($u)))).' ';
            $best = 0;
            foreach ($names as $name) {
                $n = preg_quote($name, '/');
                if (! preg_match('/\b'.$n.'\b/', $text)) {
                    continue;
                }
                $vocative = preg_match('/(?:[,;:!?]\s*|\b'.self::GREETING.'\s*,?\s+|^\s*)'.$n.'\b/', $text)
                    || preg_match('/\b'.$n.'\s*[,!?]/', $text);
                $best = max($best, $vocative ? self::VOCATIVE_WEIGHT : self::MENTION_WEIGHT);
            }
            $score += $best;
        }

        return $score;
    }

    /** Rótulo igual ao primeiro nome ou ao nome completo da pessoa (normalizados). */
    private static function labelIs(array $speaker, ?array $person): bool
    {
        if ($person === null) {
            return false;
        }
        $label = FeedbackEmployeeDuplicateFinder::normalizeName($speaker['label']);

        return $label === $person['full'] || $label === $person['first'];
    }
}
