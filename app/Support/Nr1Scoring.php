<?php

namespace App\Support;

use App\Models\SurveyTemplateQuestion;

class Nr1Scoring
{
    /**
     * Score de risco (0 = sem risco, 100 = risco máximo), a partir da escala Likert 1–5.
     */
    public static function normalizedRiskScore(SurveyTemplateQuestion $question, int $likert): float
    {
        $v = $question->reverse_score ? (6 - $likert) : $likert;

        return (($v - 1) / 4) * 100;
    }

    /**
     * Faixas por tercis na escala 0–100: verde 0–33, amarelo 34–66, vermelho 67–100.
     */
    public static function riskLevel(float $score): string
    {
        $greenMax = (float) config('nr1.risk_thresholds.green_max', 33);
        $yellowMax = (float) config('nr1.risk_thresholds.yellow_max', 66);

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

        return (float) ($map['default'] ?? 45);
    }
}
