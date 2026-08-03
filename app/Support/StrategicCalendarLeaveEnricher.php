<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\EmployeeLeaveStatus;
use App\Models\EmployeeLeave;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Injeta períodos de férias (EmployeeLeave) no calendário estratégico — só leitura.
 * Visível para admins Talents e administradores da empresa (não colaboradores comuns).
 */
final class StrategicCalendarLeaveEnricher
{
    public const KIND = 'leave';

    public const KIND_LABEL = 'Férias';

    private const MAX_DAYS_PER_LEAVE = 366;

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    public static function enrich(
        Collection $items,
        Carbon $rangeStart,
        Carbon $rangeEnd,
        ?int $companyId = null,
    ): Collection {
        $leaves = self::occurrencesForRange($rangeStart, $rangeEnd, $companyId);

        if ($leaves->isEmpty()) {
            return $items;
        }

        return $items
            ->concat($leaves)
            ->sortBy([['occurs_on', 'asc'], ['kind', 'asc'], ['source_id', 'asc']])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function occurrencesForRange(
        Carbon $rangeStart,
        Carbon $rangeEnd,
        ?int $companyId = null,
    ): Collection {
        $start = $rangeStart->copy()->startOfDay();
        $end = $rangeEnd->copy()->endOfDay();

        $query = EmployeeLeave::query()
            ->with(['company:id,name', 'employee:id,name'])
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->orderBy('start_date')
            ->orderBy('id');

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        $out = collect();

        foreach ($query->get() as $leave) {
            foreach (self::expandLeave($leave, $start, $end) as $occurrence) {
                $out->push($occurrence);
            }
        }

        return $out->values();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function expandLeave(EmployeeLeave $leave, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        /** @var Carbon $leaveStart */
        $leaveStart = $leave->start_date->copy()->startOfDay();
        /** @var Carbon $leaveEnd */
        $leaveEnd = $leave->end_date->copy()->startOfDay();

        if ($leaveEnd->lt($leaveStart)) {
            return [];
        }

        $loopStart = $leaveStart->gt($rangeStart) ? $leaveStart->copy() : $rangeStart->copy()->startOfDay();
        $loopEnd = $leaveEnd->lt($rangeEnd) ? $leaveEnd->copy() : $rangeEnd->copy()->startOfDay();

        if ($loopStart->gt($loopEnd)) {
            return [];
        }

        $name = trim((string) ($leave->employee_name ?: $leave->employee?->name ?: 'Colaborador'));
        $status = $leave->status instanceof EmployeeLeaveStatus
            ? $leave->status
            : EmployeeLeaveStatus::tryFrom((string) $leave->status);
        $statusLabel = $status?->label() ?? (string) $leave->getRawOriginal('status');
        $notes = trim((string) ($leave->notes ?? ''));

        $occurrences = [];
        $current = $loopStart->copy();
        $safety = 0;

        while ($current->lte($loopEnd) && $safety < self::MAX_DAYS_PER_LEAVE) {
            $iso = $current->toDateString();
            $periodLabel = $leaveStart->equalTo($leaveEnd)
                ? $leaveStart->format('d/m/Y')
                : $leaveStart->format('d/m/Y').' a '.$leaveEnd->format('d/m/Y');
            $isMultiDay = ! $leaveStart->equalTo($leaveEnd);

            $occurrences[] = [
                'id' => 'leave-'.$leave->id.'-'.$iso,
                'source_id' => $leave->id,
                'source_type' => 'leave',
                'title' => 'Férias — '.$name,
                'description' => trim($statusLabel.' · '.$periodLabel.($notes !== '' ? ' — '.$notes : '')),
                'kind' => self::KIND,
                // Mesmo padrão do expander: uma ocorrência por dia + ends_on/range_starts_on
                // para a tirinha contínua no mês (packWeekSpanningSegments).
                'occurs_on' => $iso,
                'ends_on' => $isMultiDay ? $leaveEnd->toDateString() : null,
                'range_starts_on' => $leaveStart->toDateString(),
                'leave_starts_on' => $leaveStart->toDateString(),
                'leave_ends_on' => $leaveEnd->toDateString(),
                'company_id' => $leave->company_id,
                'company' => $leave->company
                    ? ['id' => $leave->company->id, 'name' => $leave->company->name]
                    : null,
                'recurrence' => null,
                'recurrence_label' => null,
                'recurrence_ends_on' => null,
                'attachments' => [],
                'completed' => false,
                'completed_at' => null,
                'completed_by_user_id' => null,
                'can_manage' => false,
                'agenda' => null,
                'agenda_label' => null,
                'status' => $status?->value,
                'status_label' => $statusLabel,
            ];
            $current->addDay();
            $safety++;
        }

        return $occurrences;
    }

    /**
     * @param  array<string, string>  $labels
     * @return array<string, string>
     */
    public static function mergeKindLabels(array $labels): array
    {
        $labels[self::KIND] = self::KIND_LABEL;

        return $labels;
    }
}
