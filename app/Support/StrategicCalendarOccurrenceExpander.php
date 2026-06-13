<?php

namespace App\Support;

use App\Enums\StrategicCalendarRecurrence;
use App\Models\StrategicCalendarItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StrategicCalendarOccurrenceExpander
{
    private const MAX_OCCURRENCES_PER_ITEM = 500;

    public static function baseQueryForRange(
        Builder $query,
        Carbon $rangeStart,
        Carbon $rangeEnd,
    ): Builder {
        $start = $rangeStart->toDateString();
        $end = $rangeEnd->toDateString();

        return $query->where(function (Builder $q) use ($start, $end) {
            $q->whereBetween('occurs_on', [$start, $end])
                ->orWhere(function (Builder $q2) use ($start, $end) {
                    $q2->whereNotNull('recurrence')
                        ->where('occurs_on', '<=', $end)
                        ->where(function (Builder $q3) use ($start) {
                            $q3->whereNull('recurrence_ends_on')
                                ->orWhere('recurrence_ends_on', '>=', $start);
                        });
                });
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function expandCollection(
        Collection $items,
        Carbon $rangeStart,
        Carbon $rangeEnd,
        ?string $attachmentDownloadRouteName = 'admin.strategic-calendar.attachment-download',
    ): Collection {
        $out = collect();

        foreach ($items as $item) {
            foreach (self::occurrencesForItem($item, $rangeStart, $rangeEnd, $attachmentDownloadRouteName) as $occurrence) {
                $out->push($occurrence);
            }
        }

        return $out->sortBy([
            ['occurs_on', 'asc'],
            ['source_id', 'asc'],
        ])->values();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function occurrencesForItem(
        StrategicCalendarItem $item,
        Carbon $rangeStart,
        Carbon $rangeEnd,
        ?string $attachmentDownloadRouteName = 'admin.strategic-calendar.attachment-download',
    ): array {
        $anchor = $item->occurs_on->copy()->startOfDay();
        $rangeStart = $rangeStart->copy()->startOfDay();
        $rangeEnd = $rangeEnd->copy()->endOfDay();

        if (! $item->recurrence instanceof StrategicCalendarRecurrence) {
            if ($anchor->between($rangeStart, $rangeEnd)) {
                return [self::toOccurrenceArray($item, $anchor, $attachmentDownloadRouteName)];
            }

            return [];
        }

        $hardEnd = $item->recurrence_ends_on
            ? $item->recurrence_ends_on->copy()->endOfDay()
            : $rangeEnd->copy()->addYears(5);

        $iterationEnd = $hardEnd->lt($rangeEnd) ? $hardEnd : $rangeEnd;

        $current = $anchor->copy();
        $safety = 0;
        while ($current->lt($rangeStart) && $safety < self::MAX_OCCURRENCES_PER_ITEM) {
            $next = self::advance($current, $item->recurrence);
            if ($next->gt($current)) {
                $current = $next;
            } else {
                break;
            }
            $safety++;
        }

        $occurrences = [];
        $safety = 0;
        while ($current->lte($iterationEnd) && $safety < self::MAX_OCCURRENCES_PER_ITEM) {
            if ($current->between($rangeStart, $rangeEnd)) {
                $occurrences[] = self::toOccurrenceArray($item, $current, $attachmentDownloadRouteName);
            }

            $next = self::advance($current, $item->recurrence);
            if ($next->lte($current)) {
                break;
            }
            $current = $next;
            $safety++;
        }

        return $occurrences;
    }

    private static function advance(
        Carbon $current,
        StrategicCalendarRecurrence $recurrence,
    ): Carbon {
        return match ($recurrence) {
            StrategicCalendarRecurrence::Weekly => $current->copy()->addWeek(),
            StrategicCalendarRecurrence::Biweekly => $current->copy()->addWeeks(2),
            StrategicCalendarRecurrence::Monthly => $current->copy()->addMonthNoOverflow(),
            StrategicCalendarRecurrence::Annual => $current->copy()->addYear(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function toOccurrenceArray(
        StrategicCalendarItem $item,
        Carbon $date,
        ?string $attachmentDownloadRouteName,
    ): array {
        $iso = $date->toDateString();

        return [
            'id' => $item->id.'-'.$iso,
            'source_id' => $item->id,
            'title' => $item->title,
            'description' => $item->description,
            'kind' => $item->kind instanceof \BackedEnum ? $item->kind->value : $item->kind,
            'occurs_on' => $iso,
            'company_id' => $item->company_id,
            'company' => $item->relationLoaded('company') && $item->company
                ? ['id' => $item->company->id, 'name' => $item->company->name]
                : null,
            'recurrence' => $item->recurrence instanceof \BackedEnum ? $item->recurrence->value : $item->recurrence,
            'recurrence_label' => $item->recurrence?->label(),
            'recurrence_ends_on' => $item->recurrence_ends_on?->toDateString(),
            'attachments' => self::attachmentsPayload($item, $attachmentDownloadRouteName),
        ];
    }

    /**
     * @return list<array{id:int, name:string, url:string|null}>
     */
    private static function attachmentsPayload(
        StrategicCalendarItem $item,
        ?string $attachmentDownloadRouteName,
    ): array {
        if (! $item->relationLoaded('attachments')) {
            return [];
        }

        return $item->attachments->map(fn ($attachment) => [
            'id' => $attachment->id,
            'name' => $attachment->downloadName(),
            'url' => $attachmentDownloadRouteName
                ? route($attachmentDownloadRouteName, $attachment->id)
                : null,
        ])->values()->all();
    }
}
