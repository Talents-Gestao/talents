<?php

namespace App\Support;

use App\Models\SurveyTemplateQuestion;

class Nr1Scoring
{
    /**
     * Valor Likert efetivo (1–5) após aplicar inversão do item, se houver.
     * Quanto maior, maior o risco na dimensão.
     */
    public static function effectiveLikertValue(SurveyTemplateQuestion $question, int $likert): float
    {
        return (float) ($question->reverse_score ? (6 - $likert) : $likert);
    }

    /**
     * Faixas por tercis na escala Likert 1–5: verde ≤2,33, amarelo ≤3,66, vermelho >3,66.
     */
    public static function riskLevel(float $score): string
    {
        $greenMax = (float) config('nr1.risk_thresholds.green_max', 2.33);
        $yellowMax = (float) config('nr1.risk_thresholds.yellow_max', 3.66);

        if ($score <= $greenMax) {
            return 'green';
        }

        if ($score <= $yellowMax) {
            return 'yellow';
        }

        return 'red';
    }

    public static function riskLabel(?string $level): string
    {
        if ($level === null || $level === '') {
            return '—';
        }

        return (string) (config('nr1.risk_labels.'.$level) ?? strtoupper($level));
    }

    public static function segmentRiskBenchmark(?string $segment): float
    {
        $map = config('nr1.segment_risk_benchmarks', []);
        $key = strtolower((string) $segment);

        if ($key !== '' && isset($map[$key])) {
            return (float) $map[$key];
        }

        return (float) ($map['default'] ?? 2.80);
    }
}
