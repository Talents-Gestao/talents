<?php

declare(strict_types=1);

namespace App\Support\Rhid;

/**
 * Primeira impressão da gestão de ponto: pontualidade, faltas não justificadas e atestados.
 *
 * @phpstan-type RankingRow array{
 *     id_person: int,
 *     nome: string,
 *     percentual: float|int,
 *     dias_esperados: int,
 *     dias_cumpridos: int,
 *     dias_faltas_nao_justificadas: int,
 *     dias_atestado_excluidos: int,
 *     completou_mes_todo?: bool
 * }
 */
final class PontoPeopleSnapshot
{
    /**
     * @param  list<array<string, mixed>>  $ranking
     * @param  array<string, mixed>  $resumo
     * @return array<string, mixed>
     */
    public static function fromComplianceRanking(array $ranking, array $resumo = []): array
    {
        $rows = [];
        foreach ($ranking as $row) {
            if (! is_array($row)) {
                continue;
            }
            $esperados = (int) ($row['dias_esperados'] ?? 0);
            $cumpridos = (int) ($row['dias_cumpridos'] ?? 0);
            $faltas = (int) ($row['dias_faltas_nao_justificadas'] ?? max(0, $esperados - $cumpridos));
            $rows[] = [
                'id_person' => (int) ($row['id_person'] ?? 0),
                'nome' => (string) ($row['nome'] ?? '—'),
                'percentual' => round((float) ($row['percentual'] ?? 0), 1),
                'dias_esperados' => $esperados,
                'dias_cumpridos' => $cumpridos,
                'dias_faltas_nao_justificadas' => $faltas,
                'dias_atestado_excluidos' => (int) ($row['dias_atestado_excluidos'] ?? 0),
                'completou_mes_todo' => (bool) ($row['completou_mes_todo'] ?? false),
            ];
        }

        $evaluated = count($rows);
        $sumPct = 0.0;
        $faltasTotal = 0;
        $atestadoTotal = 0;
        foreach ($rows as $row) {
            $sumPct += $row['percentual'];
            $faltasTotal += $row['dias_faltas_nao_justificadas'];
            $atestadoTotal += $row['dias_atestado_excluidos'];
        }

        $pontualidade = $rows;
        usort($pontualidade, static function (array $a, array $b): int {
            $byPct = $b['percentual'] <=> $a['percentual'];
            if ($byPct !== 0) {
                return $byPct;
            }

            return strcasecmp($a['nome'], $b['nome']);
        });

        $faltas = $rows;
        usort($faltas, static function (array $a, array $b): int {
            $byFaltas = $b['dias_faltas_nao_justificadas'] <=> $a['dias_faltas_nao_justificadas'];
            if ($byFaltas !== 0) {
                return $byFaltas;
            }

            return strcasecmp($a['nome'], $b['nome']);
        });
        $faltas = array_values(array_filter(
            $faltas,
            static fn (array $r): bool => $r['dias_faltas_nao_justificadas'] > 0,
        ));

        $atestados = array_values(array_filter(
            $rows,
            static fn (array $r): bool => $r['dias_atestado_excluidos'] > 0,
        ));
        usort($atestados, static function (array $a, array $b): int {
            $byAtest = $b['dias_atestado_excluidos'] <=> $a['dias_atestado_excluidos'];
            if ($byAtest !== 0) {
                return $byAtest;
            }

            return strcasecmp($a['nome'], $b['nome']);
        });

        return [
            'has_data' => $evaluated > 0,
            'evaluated_count' => $evaluated,
            'punctuality_avg_percent' => $evaluated > 0 ? round($sumPct / $evaluated, 1) : null,
            'unjustified_absence_days' => $faltasTotal,
            'atestado_days' => $atestadoTotal,
            'ranking_pontualidade' => array_values($pontualidade),
            'ranking_faltas' => $faltas,
            'ranking_atestados' => $atestados,
            'resumo' => $resumo,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function empty(): array
    {
        return self::fromComplianceRanking([]);
    }
}
