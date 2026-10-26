<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Survey;

/**
 * Visão do Comercial limitado: metadados da campanha e resultados agregados já filtrados pela política
 * de grupo mínimo (a mesma do painel). Lista branca explícita: nunca resposta, respondente, item,
 * demografia ou texto livre, e nada de campanha que não tenha sido liberada.
 */
class Nr1CommercialSerializer
{
    public function __construct(private readonly PsychosocialDashboardBuilder $builder) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(Survey $survey): array
    {
        return [
            'id' => $survey->id,
            'title' => $survey->title,
            'company_name' => $survey->company?->name,
            'status' => $survey->status,
            'starts_at' => $survey->starts_at?->format('d/m/Y'),
            'ends_at' => $survey->ends_at?->format('d/m/Y'),
            'released_at' => $survey->commercial_released_at?->format('d/m/Y'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Survey $survey): array
    {
        $dashboard = $this->builder->build($survey);

        return array_merge($this->summary($survey), [
            'min_group_size' => $dashboard['min_group_size'],
            'respondents' => $dashboard['respondents'],
            'method_label' => $dashboard['method']['label'],
            'overall' => $this->cut($dashboard['overall']),
            'dimensions' => array_map(fn (array $d) => $this->dimension($d), $dashboard['dimensions']),
            'sectors' => array_map(fn (array $s) => array_merge(
                ['name' => $s['department_name']],
                $this->cut($s),
                ['dimensions' => array_map(fn (array $d) => $this->dimension($d), $s['dimensions'])],
            ), $dashboard['sectors']),
            'others' => $dashboard['others'] === null ? null : array_merge(
                ['name' => $dashboard['others']['name']],
                $this->cut($dashboard['others']),
                ['dimensions' => array_map(fn (array $d) => $this->dimension($d), $dashboard['others']['dimensions'])],
            ),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $row
     * @return array{n: int, score: string, band_label: string}|null
     */
    private function cut(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        return [
            'n' => (int) $row['n'],
            'score' => (string) $row['score'],
            'band_label' => (string) $row['band_label'],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function dimension(array $row): array
    {
        return array_merge(['title' => (string) $row['title']], $this->cut($row));
    }
}
