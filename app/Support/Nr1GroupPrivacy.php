<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Survey;
use App\Models\SurveyResult;
use Illuminate\Support\Carbon;

/**
 * Política única de confidencialidade de grupos pequenos. Só decide o que pode ser EXIBIDO:
 * nenhuma resposta sai do cálculo; toda saída por setor (painel, tela de resultados, PDFs,
 * exportações e IA) passa por aqui.
 */
final class Nr1GroupPrivacy
{
    /** Piso do mínimo de respondentes por grupo; a campanha só pode subir, nunca descer. */
    public const FLOOR = 5;

    public static function minFor(Survey $survey): int
    {
        return max(self::FLOOR, (int) ($survey->min_group_size ?? self::FLOOR));
    }

    /**
     * Supressão em cascata: oculta n < mínimo e, enquanto a soma dos ocultos ficar abaixo do mínimo,
     * oculta também o menor setor visível. Assim nenhum grupo pequeno sai por subtração.
     *
     * @param  array<int, int>  $respondentsByDepartment  department_id => n
     * @return list<int> department_id publicáveis
     */
    public static function publishableDepartmentIds(array $respondentsByDepartment, int $minGroupSize): array
    {
        $ordered = collect($respondentsByDepartment)
            ->map(fn (int $n, int $departmentId) => ['id' => $departmentId, 'n' => $n])
            ->sort(fn (array $a, array $b) => [$a['n'], $a['id']] <=> [$b['n'], $b['id']])
            ->values();

        [$hidden, $visible] = $ordered->partition(fn (array $sector) => $sector['n'] < $minGroupSize);
        $hiddenSum = $hidden->sum('n');
        $visible = $visible->values();

        while ($hiddenSum > 0 && $hiddenSum < $minGroupSize && $visible->isNotEmpty()) {
            $hiddenSum += $visible->shift()['n'];
        }

        return $visible->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    /**
     * Setores publicáveis da campanha, a partir dos resultados gravados (mesma fonte do painel).
     *
     * @return list<int>
     */
    public static function publishableDepartmentIdsForSurvey(Survey $survey): array
    {
        $counts = SurveyResult::query()
            ->where('survey_id', $survey->id)
            ->whereNotNull('department_id')
            ->whereNull('survey_template_section_id')
            ->pluck('respondent_count', 'department_id')
            ->map(fn ($n) => (int) $n)
            ->all();

        return self::publishableDepartmentIds($counts, self::minFor($survey));
    }

    /**
     * Mês de conclusão ("2026-10"): única granularidade de data que sai do sistema.
     */
    public static function month(\DateTimeInterface|string|null $date): ?string
    {
        if ($date === null) {
            return null;
        }

        return Carbon::parse($date)->format('Y-m');
    }
}
