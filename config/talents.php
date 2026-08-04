<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Meta de faturamento mensal (dashboard admin)
    |--------------------------------------------------------------------------
    | Valor em centavos usado no gauge "Meta mensal" da Home.
    */
    'dashboard' => [
        'monthly_revenue_goal_cents' => (int) env('TALENTS_DASHBOARD_MONTHLY_GOAL_CENTS', 2_000_000),
    ],
];
