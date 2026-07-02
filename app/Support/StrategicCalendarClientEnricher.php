<?php

namespace App\Support;

use App\Models\Company;
use App\Models\StrategicCalendarCompletion;
use App\Models\TaskCard;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class StrategicCalendarClientEnricher
{
    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    public static function enrich(Collection $items, Company $company, Carbon $start, Carbon $end): Collection
    {
        return self::withTasks(
            self::withCompletions($items, (int) $company->id),
            $company,
            $start,
            $end,
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    private static function withCompletions(Collection $items, int $companyId): Collection
    {
        $sourceIds = $items->pluck('source_id')->filter()->unique()->values();

        if ($sourceIds->isEmpty()) {
            return $items->map(fn (array $item) => $item + [
                'source_type' => 'strategic_item',
                'completed' => false,
                'completed_at' => null,
                'completed_by_user_id' => null,
            ]);
        }

        $completions = StrategicCalendarCompletion::query()
            ->where('company_id', $companyId)
            ->whereIn('strategic_calendar_item_id', $sourceIds)
            ->get()
            ->keyBy(fn (StrategicCalendarCompletion $completion) => $completion->strategic_calendar_item_id.'|'.$completion->occurs_on->toDateString());

        return $items->map(function (array $item) use ($completions) {
            $key = ($item['source_id'] ?? null).'|'.($item['occurs_on'] ?? null);
            $completion = $completions->get($key);

            return $item + [
                'source_type' => 'strategic_item',
                'completed' => $completion !== null,
                'completed_at' => $completion?->completed_at?->toIso8601String(),
                'completed_by_user_id' => $completion?->completed_by_user_id,
            ];
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    private static function withTasks(Collection $items, Company $company, Carbon $start, Carbon $end): Collection
    {
        if (! $company->hasTasksEnabled()) {
            return $items->sortBy([['occurs_on', 'asc'], ['source_id', 'asc']])->values();
        }

        $tasks = TaskCard::query()
            ->visibleToCompany((int) $company->id)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->with(['list:id,name'])
            ->orderBy('due_date')
            ->orderBy('id')
            ->get(['id', 'list_id', 'company_id', 'title', 'description', 'due_date', 'completed_at'])
            ->map(fn (TaskCard $card) => [
                'id' => 'task-'.$card->id,
                'source_id' => $card->id,
                'source_type' => 'task',
                'title' => $card->title,
                'description' => $card->description,
                'kind' => 'task',
                'occurs_on' => $card->due_date?->toDateString(),
                'company_id' => $card->company_id,
                'company' => ['id' => $company->id, 'name' => $company->name],
                'recurrence' => null,
                'recurrence_label' => null,
                'recurrence_ends_on' => null,
                'attachments' => [],
                'completed' => $card->completed_at !== null,
                'completed_at' => $card->completed_at?->toIso8601String(),
                'completed_by_user_id' => null,
                'list_title' => $card->list?->name,
            ]);

        return $items
            ->concat($tasks)
            ->sortBy([['occurs_on', 'asc'], ['kind', 'asc'], ['source_id', 'asc']])
            ->values();
    }
}
