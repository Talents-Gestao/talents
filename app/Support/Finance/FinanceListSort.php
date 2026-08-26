<?php

declare(strict_types=1);

namespace App\Support\Finance;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Ordenação partilhada das listagens de contas a pagar e a receber.
 *
 * Default: vencimento (mais recente primeiro). A opção «data de pagamento /
 * recebimento» usa paid_at; itens sem data efetiva ficam no fim, desempate por vencimento.
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
                ->orderByDesc('due_date')
                ->orderByDesc('id');

            return;
        }

        $query->orderByDesc('due_date')->orderByDesc('id');
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

                    $dueCmp = self::sortKey($b['due_date'] ?? null) <=> self::sortKey($a['due_date'] ?? null);
                    if ($dueCmp !== 0) {
                        return $dueCmp;
                    }

                    return self::rowId($b) <=> self::rowId($a);
                }

                $cmp = self::sortKey($b[$column] ?? null) <=> self::sortKey($a[$column] ?? null);
                if ($cmp !== 0) {
                    return $cmp;
                }

                return self::rowId($b) <=> self::rowId($a);
            })
            ->values();
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
