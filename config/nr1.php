<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Metodologia NR-1 / COPSOQ
    |--------------------------------------------------------------------------
    |
    | Média ponderada das respostas Likert 1–5 (com inversão por item quando
    | reverse_score=true). Quanto maior a média, maior o risco psicossocial.
    | Tercis: favorável ≤ 2,33 · intermediário ≤ 3,66 · elevado > 3,66.
    |
    */

    'methodology' => 'copsoq',

    'scale' => '1-5',

    'likert_min' => 1,

    'likert_max' => 5,

    'tercile_cutoffs_likert' => [2.33, 3.66],

    'risk_thresholds' => [
        'green_max' => 2.33,
        'yellow_max' => 3.66,
    ],

    'risk_labels' => [
        'green' => 'Situação favorável',
        'yellow' => 'Risco intermediário',
        'red' => 'Risco elevado',
    ],

    /*
    | Benchmark médio de risco estimado por segmento (escala Likert 1–5).
    */
    'segment_risk_benchmarks' => [
        'tecnologia' => 2.68,
        'saude' => 2.92,
        'educacao' => 2.80,
        'industria' => 2.80,
        'default' => 2.80,
    ],

    'benchmark_alert_margin' => 0.20,

    'trend_change_threshold' => 0.25,

];
