<?php

declare(strict_types=1);

namespace App\Services\Feedback;

use App\Models\Company;
use App\Models\FeedbackSession;
use App\Models\User;

class FeedbackTeamAnalyticsService
{
    /**
     * @return array{
     *   thermometer: array{labels: list<string>, series: list<int>},
     *   perceptions: array{labels: list<string>, series: list<int>},
     *   timeline: array{labels: list<string>, series: list<int>},
     *   strengths: list<string>,
     *   weaknesses: list<string>,
     *   completed_count: int,
     * }
     */
    public function forCompany(Company $company, ?User $viewer = null): array
    {
        $query = FeedbackSession::query()
            ->where('company_id', $company->id)
            ->where('status', 'completed')
            ->with(['answers.question']);

        if ($viewer && $viewer->isCompanyUser() && ! $viewer->isSuperAdmin()) {
            $query->where('leader_user_id', $viewer->id);
        }

        $sessions = $query->get();

        $thermometerLabels = ['Excelente', 'Muito bom', 'Bom', 'Regular', 'Ruim'];
        $thermometerKeys = ['excelente', 'muito_bom', 'bom', 'regular', 'ruim'];
        $thermometerCounts = array_fill_keys($thermometerKeys, 0);

        $perceptionLabels = ['Acima', 'Dentro', 'Abaixo'];
        $perceptionKeys = ['acima', 'dentro', 'abaixo'];
        $behaviorCounts = array_fill_keys($perceptionKeys, 0);
        $performanceCounts = array_fill_keys($perceptionKeys, 0);

        $strengths = [];
        $weaknesses = [];

        foreach ($sessions as $session) {
            foreach ($session->answers as $answer) {
                $key = $answer->question?->key;
                $text = is_string($answer->value_text) ? trim($answer->value_text) : '';

                if ($key === 'termometro_nivel' && is_string($answer->value_text)) {
                    $val = $answer->value_text;
                    if (isset($thermometerCounts[$val])) {
                        $thermometerCounts[$val]++;
                    }
                }

                if ($key === 'perc_comportamento' && isset($behaviorCounts[$answer->value_text])) {
                    $behaviorCounts[$answer->value_text]++;
                }

                if ($key === 'perc_desempenho' && isset($performanceCounts[$answer->value_text])) {
                    $performanceCounts[$answer->value_text]++;
                }

                if ($key === 'conquistas_lista' && $answer->value_json) {
                    foreach ((array) $answer->value_json as $item) {
                        if (is_string($item) && $item !== '') {
                            $strengths[] = $item;
                        }
                    }
                }

                if (in_array($key, ['perc_comportamento', 'perc_desempenho'], true) && $answer->value_text === 'abaixo') {
                    $weaknesses[] = $session->employee?->name ?? 'Colaborador';
                }
            }
        }

        $timeline = $this->buildTimeline($company, $viewer);

        return [
            'thermometer' => [
                'labels' => $thermometerLabels,
                'series' => array_map(fn (string $k) => $thermometerCounts[$k], $thermometerKeys),
            ],
            'perceptions' => [
                'labels' => $perceptionLabels,
                'series' => [
                    array_map(fn (string $k) => $behaviorCounts[$k], $perceptionKeys),
                    array_map(fn (string $k) => $performanceCounts[$k], $perceptionKeys),
                ],
            ],
            'timeline' => $timeline,
            'strengths' => $this->topTerms($strengths, 5),
            'weaknesses' => array_values(array_unique($weaknesses)),
            'completed_count' => $sessions->count(),
        ];
    }

    /**
     * @return array{labels: list<string>, series: list<int>}
     */
    private function buildTimeline(Company $company, ?User $viewer = null): array
    {
        $query = FeedbackSession::query()
            ->where('company_id', $company->id)
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->orderBy('completed_at');

        if ($viewer && $viewer->isCompanyUser() && ! $viewer->isSuperAdmin()) {
            $query->where('leader_user_id', $viewer->id);
        }

        $sessions = $query->get(['completed_at']);

        $grouped = [];
        foreach ($sessions as $session) {
            $key = $session->completed_at?->format('m/Y') ?? '';
            if ($key === '') {
                continue;
            }
            $grouped[$key] = ($grouped[$key] ?? 0) + 1;
        }

        return [
            'labels' => array_keys($grouped),
            'series' => array_values($grouped),
        ];
    }

    /**
     * @param  list<string>  $items
     * @return list<string>
     */
    private function topTerms(array $items, int $limit): array
    {
        if ($items === []) {
            return [];
        }

        $freq = array_count_values(array_map('mb_strtolower', $items));
        arsort($freq);

        return array_slice(array_keys($freq), 0, $limit);
    }
}
