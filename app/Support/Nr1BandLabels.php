<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Survey;
use App\Models\SurveyMethodVersion;

/**
 * Mapa único dos rótulos de EXIBIÇÃO das faixas, por método da campanha. O valor gravado em survey_results.risk_level continua
 * green|yellow|red; só o texto muda conforme o método da campanha.
 *
 * talents39_v1 (V11): a faixa é percepção do questionário, não classificação de risco ocupacional.
 * production_v1: mantém Leve/Moderado/Grave das campanhas antigas.
 */
final class Nr1BandLabels
{
    public const V11 = [
        'green' => 'Menor sinalização',
        'yellow' => 'Atenção',
        'red' => 'Maior sinalização',
    ];

    public const LEGACY = [
        'green' => 'Leve',
        'yellow' => 'Moderado',
        'red' => 'Grave',
    ];

    /**
     * Rótulos do relatório da própria campanha.
     *
     * @return array<string, string>
     */
    public static function forSurvey(Survey $survey): array
    {
        return self::labels(self::methodCode($survey));
    }

    public static function methodCode(Survey $survey): ?string
    {
        // Consulta direta: carregar a relação a mandaria junto no prop `survey` das telas.
        return SurveyMethodVersion::query()->whereKey($survey->method_version_id)->value('code');
    }

    /**
     * @return array<string, string>
     */
    public static function labels(?string $methodCode = null): array
    {
        return $methodCode === SurveyMethodVersion::CODE_TALENTS39_V1 ? self::V11 : self::LEGACY;
    }

    public static function label(?string $level, ?string $methodCode = null): string
    {
        if ($level === null || $level === '') {
            return '—';
        }

        return self::labels($methodCode)[$level] ?? strtoupper($level);
    }

    public static function legend(?string $methodCode = null): string
    {
        $labels = self::labels($methodCode);

        return $methodCode === SurveyMethodVersion::CODE_TALENTS39_V1
            ? "1,00–2,33 {$labels['green']} · 2,34–3,66 {$labels['yellow']} · 3,67–5,00 {$labels['red']}"
            : "até 2,33 {$labels['green']} · acima de 2,33 até 3,66 {$labels['yellow']} · acima de 3,66 {$labels['red']}";
    }

    /**
     * Faixas para a legenda das telas: rótulo do método, intervalo e leitura.
     *
     * @return list<array{risk: string, label: string, range: string, text: string}>
     */
    public static function bands(?string $methodCode = null): array
    {
        $labels = self::labels($methodCode);
        $greenText = $methodCode === SurveyMethodVersion::CODE_TALENTS39_V1
            ? 'Menor sinalização de fatores desfavoráveis.'
            : 'Menor presença de fatores desfavoráveis.';

        return [
            ['risk' => 'green', 'label' => $labels['green'], 'range' => '1,00–2,33', 'text' => $greenText],
            ['risk' => 'yellow', 'label' => $labels['yellow'], 'range' => '2,34–3,66', 'text' => 'Priorizar a investigação e o acompanhamento.'],
            ['risk' => 'red', 'label' => $labels['red'], 'range' => '3,67–5,00', 'text' => 'Requer análise prioritária dos fatores sinalizados.'],
        ];
    }

    /**
     * "Em atenção" = índice a partir de 2,34 (faixa atenção ou maior sinalização).
     */
    public static function isAttention(string|float $score): bool
    {
        return round((float) $score, 2) >= 2.34;
    }
}
