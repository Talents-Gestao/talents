<?php

namespace App\Support;

/**
 * Converte valor em centavos para extenso em reais (pt-BR).
 */
final class BrlExtenso
{
    private const UNIDADES = [
        '', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove',
    ];

    private const DEZ_A_DEZENOVE = [
        'dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove',
    ];

    private const DEZENAS = [
        '', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa',
    ];

    private const CENTENAS = [
        '', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos',
    ];

    public static function fromCents(int $cents): string
    {
        if ($cents === 0) {
            return 'Zero reais';
        }
        $negativo = $cents < 0;
        $cents = abs($cents);
        $reais = intdiv($cents, 100);
        $centavos = $cents % 100;

        $partes = [];
        if ($reais > 0) {
            $partes[] = self::extensoInteiro($reais).($reais === 1 ? ' real' : ' reais');
        } else {
            $partes[] = 'zero real';
        }

        if ($centavos > 0) {
            $partes[] = 'e '.self::extensoInteiro($centavos).($centavos === 1 ? ' centavo' : ' centavos');
        }

        $s = implode(' ', $partes);
        if ($negativo) {
            $s = 'Menos '.$s;
        }

        return mb_strtoupper(mb_substr($s, 0, 1)).mb_substr($s, 1);
    }

    private static function extensoInteiro(int $n): string
    {
        if ($n === 0) {
            return 'zero';
        }
        if ($n < 1000) {
            return self::grupoAte999($n);
        }
        if ($n < 1_000_000) {
            $th = intdiv($n, 1000);
            $rem = $n % 1000;
            $head = $th === 1 ? 'mil' : self::grupoAte999($th).' mil';

            return $rem === 0 ? $head : $head.' e '.self::grupoAte999($rem);
        }
        $mill = intdiv($n, 1_000_000);
        $rem = $n % 1_000_000;
        $head = $mill === 1
            ? 'um milhão'
            : self::extensoInteiro($mill).' milhões';

        return $rem === 0 ? $head : $head.' e '.self::extensoInteiro($rem);
    }

    private static function grupoAte999(int $n): string
    {
        if ($n === 0) {
            return '';
        }
        if ($n === 100) {
            return 'cem';
        }
        $c = intdiv($n, 100);
        $resto = $n % 100;
        $partes = [];
        if ($c > 0) {
            $partes[] = self::CENTENAS[$c];
        }
        if ($resto > 0) {
            if ($resto < 10) {
                $partes[] = self::UNIDADES[$resto];
            } elseif ($resto < 20) {
                $partes[] = self::DEZ_A_DEZENOVE[$resto - 10];
            } else {
                $d = intdiv($resto, 10);
                $u = $resto % 10;
                $s = self::DEZENAS[$d];
                if ($u > 0) {
                    $s .= ' e '.self::UNIDADES[$u];
                }
                $partes[] = $s;
            }
        }

        return implode(' e ', array_filter($partes));
    }
}
