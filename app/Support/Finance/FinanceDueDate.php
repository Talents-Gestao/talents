<?php

declare(strict_types=1);

namespace App\Support\Finance;

/**
 * Vencimento informado nos formulários de Contas a pagar e Contas a receber (inclusive parcelas).
 *
 * Só o dia (Y-m-d) e ano entre 2000 e 2100: no <input type="date">, digitar «6» no ano gravaria
 * 0006-10-01, que a tela mostra como «01/10/6» e que fica sempre no topo da ordenação por vencimento.
 */
final class FinanceDueDate
{
    public const MIN = '2000-01-01';

    public const MAX = '2100-12-31';

    /**
     * @return list<string>
     */
    public static function rules(): array
    {
        return [
            'required',
            'date_format:Y-m-d',
            'after_or_equal:'.self::MIN,
            'before_or_equal:'.self::MAX,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $field = 'due_date'): array
    {
        $range = 'O vencimento deve estar entre 01/01/2000 e 31/12/2100.';

        return [
            "{$field}.required" => 'Informe o vencimento.',
            "{$field}.date_format" => 'Informe um vencimento válido.',
            "{$field}.after_or_equal" => $range,
            "{$field}.before_or_equal" => $range,
        ];
    }
}
