<?php

declare(strict_types=1);

namespace App\Support\Finance;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Ordenação partilhada das listagens de contas a pagar e a receber (em SQL).
 *
 * - Vencimento: due_date crescente, sem data no fim.
 * - Data de pagamento/recebimento: dia de paid_at decrescente; itens sem pagamento
 *   (pendentes/cancelados) sempre no fim, entre si por vencimento crescente.
 * Desempate determinístico: (origem, id).
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

    /**
     * Ordem padrão por aba: Pendentes e Todas = vencimento crescente;
     * Pagas/Recebidas = data de pagamento decrescente.
     */
    public static function defaultForStatus(?string $status): string
    {
        return $status === 'paid' ? self::PAID_AT : self::DUE_DATE;
    }

    public static function fromRequest(Request $request): string
    {
        $default = self::defaultForStatus($request->string('status')->toString());
        $sort = (string) $request->input('sort', $default);

        return in_array($sort, self::values(), true) ? $sort : $default;
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
        foreach (self::orderBySql($sort) as $expression) {
            $query->orderByRaw($expression);
        }
        $query->orderBy('id');
    }

    /**
     * Expressões ORDER BY (sem o desempate final), válidas em Postgres e SQLite.
     * date(paid_at) compara só o dia, como a coluna exibida.
     *
     * @return list<string>
     */
    public static function orderBySql(string $sort, string $dueColumn = 'due_date', string $paidColumn = 'paid_at'): array
    {
        if ($sort === self::PAID_AT) {
            return [
                "CASE WHEN {$paidColumn} IS NULL THEN 1 ELSE 0 END",
                "date({$paidColumn}) DESC",
                "CASE WHEN {$dueColumn} IS NULL THEN 1 ELSE 0 END",
                "{$dueColumn} ASC",
            ];
        }

        return [
            "CASE WHEN {$dueColumn} IS NULL THEN 1 ELSE 0 END",
            "{$dueColumn} ASC",
        ];
    }
}
