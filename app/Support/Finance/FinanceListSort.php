<?php

declare(strict_types=1);

namespace App\Support\Finance;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Ordenação partilhada das listagens de contas a pagar e a receber.
 *
 * Default: vencimento (mais próximos primeiro). A opção «data de pagamento /
 * recebimento» usa paid_at; itens sem data efetiva ficam no fim, desempate por vencimento ASC.
 */
final class FinanceListSort
{
    public const DUE_DATE = 'due_date';

    public const PAID_AT = 'paid_at';

    /** @return list<string> */
    public static function values(): array
    {
        return [self::DUE_DATE, self::PAID_AT];
    }

    public static function fromRequest(Request $request): string
    {
        $sort = (string) $request->input('sort', self::DUE_DATE);

        return in_array($sort, self::values(), true) ? $sort : self::DUE_DATE;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function payableOptions(): array
    {
        return [
            ['value' => self::DUE_DATE, 'label' => 'Vencimento'],
            ['value' => self::PAID_AT, 'label' => 'Data de pagamento'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function receivableOptions(): array
    {
        return [
            ['value' => self::DUE_DATE, 'label' => 'Vencimento'],
            ['value' => self::PAID_AT, 'label' => 'Data de recebimento'],
        ];
    }

    public static function applyToQuery(Builder $query, string $sort): void
    {
        if ($sort === self::PAID_AT) {
            $query
                ->orderByRaw('CASE WHEN paid_at IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('paid_at')
                ->orderBy('due_date')
                ->orderBy('id');

            return;
        }

        $query
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')
            ->orderBy('id');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    public static function sortRows(Collection $items, string $sort): Collection
    {
        $column = in_array($sort, self::values(), true) ? $sort : self::DUE_DATE;

        return $items
            ->sort(function (array $a, array $b) use ($column): int {
                if ($column === self::PAID_AT) {
                    $aNull = ! self::hasDate($a['paid_at'] ?? null);
                    $bNull = ! self::hasDate($b['paid_at'] ?? null);
                    if ($aNull !== $bNull) {
                        return $aNull <=> $bNull;
                    }
                    if (! $aNull) {
                        $cmp = self::sortKey($b['paid_at'] ?? null) <=> self::sortKey($a['paid_at'] ?? null);
                        if ($cmp !== 0) {
                            return $cmp;
                        }
                    }

                    $dueCmp = self::compareDueDatesAscending($a, $b);
                    if ($dueCmp !== 0) {
                        return $dueCmp;
                    }

                    return self::rowId($a) <=> self::rowId($b);
                }

                $dueCmp = self::compareDueDatesAscending($a, $b);
                if ($dueCmp !== 0) {
                    return $dueCmp;
                }

                return self::rowId($a) <=> self::rowId($b);
            })
            ->values();
    }

    /**
     * Vencimentos mais próximos primeiro; sem data no fim.
     *
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private static function compareDueDatesAscending(array $a, array $b): int
    {
        $aNull = ! self::hasDate($a['due_date'] ?? null);
        $bNull = ! self::hasDate($b['due_date'] ?? null);
        if ($aNull !== $bNull) {
            return $aNull <=> $bNull;
        }

        return self::sortKey($a['due_date'] ?? null) <=> self::sortKey($b['due_date'] ?? null);
    }

    private static function hasDate(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }

    private static function sortKey(mixed $value): string
    {
        if (! self::hasDate($value)) {
            return '0000-01-01';
        }

        return substr((string) $value, 0, 10);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function rowId(array $row): int
    {
        foreach (['receivable_id', 'installment_id', 'id'] as $key) {
            if (isset($row[$key]) && is_numeric($row[$key])) {
                return (int) $row[$key];
            }
        }

        return 0;
    }
}
