<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Rótulos visíveis em PT-BR (acentos e cedilha).
 */
final class PortugueseLabels
{
    /**
     * @var array<string, string>
     */
    private const SEGMENTS = [
        'servicos' => 'Serviços',
        'comercio' => 'Comércio',
        'industria' => 'Indústria',
        'saude' => 'Saúde',
        'educacao' => 'Educação',
        'alimentacao' => 'Alimentação',
        'construcao' => 'Construção',
        'tecnologia' => 'Tecnologia',
        'varejo' => 'Varejo',
        'logistica' => 'Logística',
        'financeiro' => 'Financeiro',
        'agronegocio' => 'Agronegócio',
        'hotelaria' => 'Hotelaria',
        'transporte' => 'Transporte',
    ];

    public static function segment(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        $key = mb_strtolower($trimmed);
        $ascii = strtolower(Str::ascii($key));

        return self::SEGMENTS[$key] ?? self::SEGMENTS[$ascii] ?? $trimmed;
    }
}
