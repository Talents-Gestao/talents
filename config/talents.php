<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Meta de faturamento mensal (dashboard admin) — FALLBACK
    |--------------------------------------------------------------------------
    | Usado apenas quando ainda não há valor cadastrado em
    | admin_dashboard_settings.monthly_revenue_goal_cents (Home → Meta mensal).
    | Valor em centavos. Default: R$ 20.000.
    */
    'dashboard' => [
        'monthly_revenue_goal_cents' => (int) env('TALENTS_DASHBOARD_MONTHLY_GOAL_CENTS', 2_000_000),
    ],
];
