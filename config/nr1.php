<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Metodologia NR-1 / COPSOQ
    |--------------------------------------------------------------------------
    |
    | Escala Likert 1–5 convertida para índice de risco 0–100:
    | (resposta − 1) ÷ 4 × 100, com inversão por item quando reverse_score=true.
    | Quanto maior o score, maior o risco psicossocial.
    |
    */

    'methodology' => 'copsoq',

    'scale' => '0-100',

    'likert_min' => 1,

    'likert_max' => 5,

    'tercile_cutoffs_likert' => [2.33, 3.66],

    'risk_thresholds' => [
        'green_max' => 33,
        'yellow_max' => 66,
    ],

    'risk_labels' => [
        'green' => 'Situação favorável',
        'yellow' => 'Risco intermediário',
        'red' => 'Risco elevado',
    ],

    /*
    | Benchmark médio de risco estimado por segmento (0–100; menor = melhor).
    */
    'segment_risk_benchmarks' => [
        'tecnologia' => 42,
        'saude' => 48,
        'educacao' => 45,
        'industria' => 45,
        'default' => 45,
    ],

    'benchmark_alert_margin' => 5,

    'trend_change_threshold' => 3,

];
