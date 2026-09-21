<?php

declare(strict_types=1);

/**
 * Mapa de Propósito — roteiro universal e catálogo de setores (Voz do Time).
 */
return [
    'max_answer_length' => 1000,

    'questions' => [
        'why_work' => [
            'key' => 'why_work',
            'label' => 'Por que você trabalha?',
            'dashboard_label' => 'Motivações',
        ],
        'dream' => [
            'key' => 'dream',
            'label' => 'Qual o seu sonho?',
            'dashboard_label' => 'Sonhos',
        ],
        'pain_self' => [
            'key' => 'pain_self',
            'label' => 'Qual dor você resolve?',
            'dashboard_label' => 'Dor que eu resolvo',
        ],
        'pain_sector' => [
            'key' => 'pain_sector',
            'label' => 'Qual dor o seu setor resolve?',
            'dashboard_label' => 'Dor que o setor resolve',
        ],
        'pain_company' => [
            'key' => 'pain_company',
            'label' => 'Qual dor a sua empresa resolve?',
            'dashboard_label' => 'Dor que a empresa resolve',
        ],
    ],

    'sectors' => [
        'ATENDIMENTO',
        'COMERCIAL',
        'MARKETING',
        'SUCESSO DO CLIENTE',
        'RECURSOS HUMANOS',
        'FINANCEIRO',
        'DESENVOLVIMENTO',
        'SERVIÇOS EXTERNOS',
        'LIMPEZA',
        'FISCAL',
        'CONTÁBIL',
        'DEPARTAMENTO PESSOAL',
    ],
];
