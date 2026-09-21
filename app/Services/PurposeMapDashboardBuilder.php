<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PurposeMapCampaign;
use App\Models\PurposeMapResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class PurposeMapDashboardBuilder
{
    /**
     * @return array{
     *     kpis: array<string, int|float|null>,
     *     participation_by_sector: list<array{sector: string, count: int}>,
     *     themes_by_question: array<string, list<array{theme: string, count: int, sample_excerpts: list<string>}>>,
     *     pain_alignment: array{overlap_themes: list<string>, self: list<array{theme: string, count: int}>, sector: list<array{theme: string, count: int}>, company: list<array{theme: string, count: int}>, score: float|null},
     *     motivation_and_dreams: array{why_work: list<array{theme: string, count: int}>, dream: list<array{theme: string, count: int}>, quotes: list<array{question: string, text: string, sector: string}>},
     *     insights: list<array{id: int, kind: string, message: string}>,
     *     responses: list<array<string, mixed>>,
     *     catalog_sectors: list<string>,
     *     questions: array<string, array{key: string, label: string, dashboard_label: string}>
     * }
     */
    public function build(PurposeMapCampaign $campaign, ?string $sectorFilter = null): array
    {
        $catalogSectors = config('purpose_map.sectors', []);
        $questions = config('purpose_map.questions', []);

        $query = PurposeMapResponse::query()->where('purpose_map_campaign_id', $campaign->id);
        if ($sectorFilter !== null && $sectorFilter !== '' && $sectorFilter !== 'all') {
            $query->where('sector', $sectorFilter);
        }

        /** @var Collection<int, PurposeMapResponse> $responses */
        $responses = $query->orderByDesc('id')->get();

        $bySector = $responses->groupBy('sector')->map->count();
        $participation = collect($catalogSectors)
            ->map(fn (string $sector) => [
                'sector' => $sector,
                'count' => (int) ($bySector[$sector] ?? 0),
            ])
            ->values()
            ->all();

        $sectorsWithResponses = collect($participation)->where('count', '>', 0)->count();
        $sectorsWithout = count($catalogSectors) - $sectorsWithResponses;

        $openedAt = $campaign->starts_at ?? $campaign->created_at;
        $daysOpen = $openedAt ? max(0, (int) $openedAt->diffInDays(now())) : null;

        $themeQuery = $campaign->themes()->orderByDesc('count');
        if ($sectorFilter !== null && $sectorFilter !== '' && $sectorFilter !== 'all') {
            $themeQuery->where(function ($q) use ($sectorFilter) {
                $q->where('sector', $sectorFilter)->orWhereNull('sector');
            });
        } else {
            $themeQuery->whereNull('sector');
        }

        $themes = $themeQuery->get();
        $themesByQuestion = [];
        foreach (array_keys($questions) as $key) {
            $themesByQuestion[$key] = $themes
                ->where('question_key', $key)
                ->take(8)
                ->map(fn ($t) => [
                    'theme' => $t->theme,
                    'count' => (int) $t->count,
                    'sample_excerpts' => array_values($t->sample_excerpts ?? []),
                ])
                ->values()
                ->all();
        }

        $selfThemes = collect($themesByQuestion['pain_self'] ?? []);
        $sectorThemes = collect($themesByQuestion['pain_sector'] ?? []);
        $companyThemes = collect($themesByQuestion['pain_company'] ?? []);

        $normalize = static fn (string $t) => Str::lower(trim($t));
        $selfSet = $selfThemes->pluck('theme')->map($normalize)->all();
        $sectorSet = $sectorThemes->pluck('theme')->map($normalize)->all();
        $companySet = $companyThemes->pluck('theme')->map($normalize)->all();
        $overlap = array_values(array_unique(array_intersect($selfSet, $sectorSet, $companySet)));
        if ($overlap === []) {
            $overlap = array_values(array_unique(array_merge(
                array_intersect($selfSet, $sectorSet),
                array_intersect($selfSet, $companySet),
                array_intersect($sectorSet, $companySet),
            )));
        }

        $unionCount = count(array_unique(array_merge($selfSet, $sectorSet, $companySet)));
        $alignmentScore = $unionCount > 0
            ? round((count($overlap) / max(1, min(count($selfSet) ?: 1, count($sectorSet) ?: 1, count($companySet) ?: 1))) * 100, 1)
            : null;

        $quotes = [];
        foreach ($responses->take(40) as $r) {
            foreach (['why_work' => 'Motivações', 'dream' => 'Sonhos'] as $field => $label) {
                $text = trim((string) $r->{$field});
                if ($text === '') {
                    continue;
                }
                $quotes[] = [
                    'question' => $label,
                    'text' => Str::limit($text, 180),
                    'sector' => $r->sector,
                ];
            }
        }
        $quotes = array_slice($quotes, 0, 8);

        $insights = $campaign->insights()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'kind' => $i->kind,
                'message' => $i->message,
            ])
            ->all();

        return [
            'kpis' => [
                'total_responses' => $responses->count(),
                'sectors_covered' => $sectorsWithResponses,
                'sectors_without_response' => $sectorsWithout,
                'days_open' => $daysOpen,
            ],
            'participation_by_sector' => $participation,
            'themes_by_question' => $themesByQuestion,
            'pain_alignment' => [
                'overlap_themes' => $overlap,
                'self' => $selfThemes->take(6)->values()->all(),
                'sector' => $sectorThemes->take(6)->values()->all(),
                'company' => $companyThemes->take(6)->values()->all(),
                'score' => $alignmentScore,
            ],
            'motivation_and_dreams' => [
                'why_work' => $themesByQuestion['why_work'] ?? [],
                'dream' => $themesByQuestion['dream'] ?? [],
                'quotes' => $quotes,
            ],
            'insights' => $insights,
            'responses' => $responses->map(fn (PurposeMapResponse $r) => [
                'id' => $r->id,
                'sector' => $r->sector,
                'why_work' => $r->why_work,
                'dream' => $r->dream,
                'pain_self' => $r->pain_self,
                'pain_sector' => $r->pain_sector,
                'pain_company' => $r->pain_company,
                'created_at' => $r->created_at?->toIso8601String(),
            ])->values()->all(),
            'catalog_sectors' => $catalogSectors,
            'questions' => $questions,
        ];
    }
}
