<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Dimensões V11 (Talents 39) para as sugestões do motor no plano de ação:
 * - chave lógica da dimensão (sem caixa, sem acento e sem espaços repetidos), usada para ter uma única sugestão do motor
 *   por campanha e dimensão;
 * - catálogo por dimensão (indicador e responsável sugerido como área/função), aplicado
 *   só a sugestões do motor em campanhas V11 e só em campos vazios. É sugestão revisável pela Talents: não é aceite da
 *   empresa, não é diagnóstico clínico e não afirma cumprimento legal.
 */
final class Nr1MeasureCatalog
{
    /** Regra atual do prazo sugerido pelo motor (ActionMeasuresGenerator). */
    public const DUE_MIN_DAYS = 15;

    public const DUE_MAX_DAYS = 90;

    public const DUE_DEFAULT_DAYS = 30;

    /**
     * @var array<string, array{indicator: string, responsible: string}>
     */
    public const V11 = [
        'Demandas e Sobrecarga' => [
            'indicator' => 'Percentual de equipes com metas, prazos e distribuição de tarefas revisados no período',
            'responsible' => 'Gestão de Operações',
        ],
        'Autonomia e Organização' => [
            'indicator' => 'Percentual de funções com atribuições, prioridades e níveis de decisão definidos e comunicados',
            'responsible' => 'Gestão de Pessoas (RH) com as lideranças',
        ],
        'Liderança e Gestão' => [
            'indicator' => 'Percentual de líderes com conversas periódicas de acompanhamento registradas com a equipe',
            'responsible' => 'Diretoria e Gestão de Pessoas (RH)',
        ],
        'Relacionamentos e Clima' => [
            'indicator' => 'Percentual de equipes com acordos de convivência definidos e situações de conflito tratadas pelo canal combinado',
            'responsible' => 'Gestão de Pessoas (RH)',
        ],
        'Segurança Psicológica' => [
            'indicator' => 'Percentual de manifestações recebidas pelos canais de escuta com retorno no prazo definido',
            'responsible' => 'Gestão de Pessoas (RH) e Diretoria',
        ],
        'Esgotamento e Saúde Emocional' => [
            'indicator' => 'Percentual de equipes com jornadas, pausas e períodos de pico revisados e acompanhados',
            'responsible' => 'Gestão de Pessoas (RH) e SESMT, quando houver',
        ],
        'Percepção Organizacional' => [
            'indicator' => 'Percentual de decisões e mudanças relevantes comunicadas com espaço para dúvidas e devolutiva às equipes',
            'responsible' => 'Diretoria e Comunicação Interna',
        ],
    ];

    /** Chave lógica: sem caixa, sem acento e sem espaços repetidos. */
    public static function key(string $label): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', Str::lower(Str::ascii($label))));
    }

    /** Nome da dimensão V11 a que o rótulo se refere («Demandas e sobrecarga (Operações)» → «Demandas e Sobrecarga»). */
    public static function v11Dimension(string $label): ?string
    {
        $key = self::key($label);
        foreach (array_keys(self::V11) as $dimension) {
            $dimKey = self::key($dimension);
            if ($key === $dimKey || str_starts_with($key, $dimKey.' ') || str_starts_with($key, $dimKey.'(') || str_starts_with($key, $dimKey.' -')) {
                return $dimension;
            }
        }

        return null;
    }

    /** Chave da dimensão para agrupar sugestões: a dimensão V11 quando reconhecida, senão o próprio rótulo normalizado. */
    public static function dimensionKey(string $label): string
    {
        return self::key(self::v11Dimension($label) ?? $label);
    }

    /** @return array{indicator: string, responsible: string}|null */
    public static function entry(string $label): ?array
    {
        $dimension = self::v11Dimension($label);

        return $dimension === null ? null : self::V11[$dimension];
    }

    /** Áreas/funções que o motor pode sugerir como responsável (nunca nome de pessoa). @return list<string> */
    public static function responsibleAreas(): array
    {
        return array_values(array_unique(array_column(self::V11, 'responsible')));
    }

    /** Justificativa padrão do prazo sugerido pelo motor (faixa da dimensão e regra do prazo). */
    public static function dueNote(?string $bandLabel, int $days): string
    {
        $band = filled($bandLabel) ? "Faixa da dimensão: {$bandLabel}." : 'Faixa da dimensão não disponível.';

        return sprintf(
            '%s Prazo de %d dias contados da geração da sugestão, dentro da regra do plano (de %d a %d dias).',
            $band,
            $days,
            self::DUE_MIN_DAYS,
            self::DUE_MAX_DAYS,
        );
    }

    /** Junta a ação de outra sugestão da mesma dimensão sem repetir frases já presentes. */
    public static function mergeDescriptions(string $first, string $second, int $limit = 5000): string
    {
        $sentences = fn (string $text) => array_values(array_filter(array_map('trim', preg_split('/(?<=[.!?;])\s+/u', trim($text)) ?: [])));
        $seen = array_map(fn (string $s) => self::key(rtrim($s, '.!?; ')), $sentences($first));
        $merged = trim($first);
        foreach ($sentences($second) as $sentence) {
            $key = self::key(rtrim($sentence, '.!?; '));
            if ($key === '' || in_array($key, $seen, true) || str_contains(self::key($merged), $key)) {
                continue;
            }
            $seen[] = $key;
            $merged = rtrim($merged);
            if ($merged !== '' && preg_match('/[.!?;]$/u', $merged) !== 1) {
                $merged .= '.';
            }
            $merged .= ' '.$sentence;
        }

        return mb_substr($merged, 0, $limit);
    }
}
