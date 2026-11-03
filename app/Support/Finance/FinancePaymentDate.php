<?php

declare(strict_types=1);

namespace App\Support\Finance;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Data efetiva de pagamento/recebimento (paid_at).
 *
 * O usuário informa só o dia (Y-m-d, calendário de America/Sao_Paulo). Gravamos
 * 00:00 desse dia no fuso da aplicação — o mesmo que registerPayment fazia com
 * Carbon::parse('Y-m-d'). Assim receivedBetweenCents/receivedInPeriodCents e a
 * meta da Home (whereBetween paid_at no mês) contam o lançamento no dia escolhido.
 */
final class FinancePaymentDate
{
    public const TIMEZONE = 'America/Sao_Paulo';

    public static function today(): string
    {
        return CarbonImmutable::now(self::TIMEZONE)->toDateString();
    }

    /**
     * @return list<string>
     */
    public static function rules(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'date_format:Y-m-d',
            'before_or_equal:'.self::today(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $field = 'paid_at'): array
    {
        return [
            "{$field}.required" => 'Informe a data de pagamento.',
            "{$field}.date_format" => 'Informe uma data válida.',
            "{$field}.before_or_equal" => 'A data de pagamento não pode ser futura.',
        ];
    }

    /**
     * @return list<string>
     */
    public static function reasonRules(): array
    {
        return ['nullable', 'string', 'max:500'];
    }

    /**
     * min(dia, hoje): nunca registra pagamento no futuro (ex.: série recorrente
     * criada já como paga, com vencimentos à frente).
     */
    public static function notAfterToday(string $day): string
    {
        $today = self::today();

        return $day > $today ? $today : $day;
    }

    public static function toPaidAt(?string $day): CarbonImmutable
    {
        $day = filled($day) ? (string) $day : self::today();

        return CarbonImmutable::createFromFormat('Y-m-d', $day, self::TIMEZONE)
            ->startOfDay()
            ->setTimezone((string) config('app.timezone'));
    }

    public static function sameDay(?CarbonInterface $a, ?CarbonInterface $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }

        return $a->copy()->setTimezone(self::TIMEZONE)->toDateString()
            === $b->copy()->setTimezone(self::TIMEZONE)->toDateString();
    }
}
