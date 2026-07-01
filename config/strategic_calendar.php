<?php

return [
    'max_attachment_kb' => (int) env('STRATEGIC_CALENDAR_MAX_ATTACHMENT_KB', 524288),

    'month_themes' => [
        1 => [
            'label' => 'Janeiro Branco',
            'campaign' => 'Paz e saúde mental',
            'color' => '#64748b',
            'background' => '#f8fafc',
        ],
        2 => [
            'label' => 'Fevereiro Laranja',
            'campaign' => 'Leucemia e linfoma',
            'color' => '#f97316',
            'background' => '#fff7ed',
        ],
        3 => [
            'label' => 'Março Azul-claro',
            'campaign' => 'Hidratação e bem-estar',
            'color' => '#38bdf8',
            'background' => '#f0f9ff',
        ],
        4 => [
            'label' => 'Abril Azul',
            'campaign' => 'Conscientização sobre o autismo',
            'color' => '#2563eb',
            'background' => '#eff6ff',
        ],
        5 => [
            'label' => 'Maio Amarelo',
            'campaign' => 'Hepatites e prevenção',
            'color' => '#eab308',
            'background' => '#fefce8',
        ],
        6 => [
            'label' => 'Junho Vermelho',
            'campaign' => 'Doação de sangue',
            'color' => '#dc2626',
            'background' => '#fef2f2',
        ],
        7 => [
            'label' => 'Julho Amarelo',
            'campaign' => 'Hepatites virais',
            'color' => '#ca8a04',
            'background' => '#fefce8',
        ],
        8 => [
            'label' => 'Agosto Dourado',
            'campaign' => 'Aleitamento materno',
            'color' => '#d97706',
            'background' => '#fffbeb',
        ],
        9 => [
            'label' => 'Setembro Amarelo',
            'campaign' => 'Prevenção ao suicídio',
            'color' => '#eab308',
            'background' => '#fef9c3',
        ],
        10 => [
            'label' => 'Outubro Rosa',
            'campaign' => 'Câncer de mama',
            'color' => '#ec4899',
            'background' => '#fdf2f8',
        ],
        11 => [
            'label' => 'Novembro Azul',
            'campaign' => 'Câncer de próstata',
            'color' => '#1d4ed8',
            'background' => '#eff6ff',
        ],
        12 => [
            'label' => 'Dezembro Vermelho',
            'campaign' => 'Prevenção à AIDS',
            'color' => '#ef4444',
            'background' => '#fef2f2',
        ],
    ],

    'kind_colors' => [
        'event' => [
            'label' => 'Evento',
            'color' => '#0ea5e9',
            'background' => '#e0f2fe',
        ],
        'rito' => [
            'label' => 'Rito',
            'color' => '#ef4444',
            'background' => '#fee2e2',
        ],
        'task' => [
            'label' => 'Tarefa',
            'color' => '#10b981',
            'background' => '#d1fae5',
        ],
    ],
];
