<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Relatórios NR-1 por cenário de risco geral
    |--------------------------------------------------------------------------
    |
    | Conteúdo narrativo dos três relatórios (executivo, plano de ação,
    | encaminhamento técnico) conforme o cenário agregado da campanha.
    |
    */

    'scenarios' => [
        'green' => [
            'label' => 'Baixo risco',
            'short_label' => 'Cenário geral: Baixo risco',
            'executive' => [
                'focus_heading' => 'Visão geral e oportunidades',
                'focus_intro' => 'O indicador geral aponta situação favorável. Este relatório destaca oportunidades de melhoria contínua e recomendações para consolidar boas práticas no ambiente de trabalho.',
                'recommendations_heading' => 'Recomendações',
                'recommendations' => [
                    'Manter práticas de comunicação, reconhecimento e equilíbrio entre demandas e recursos.',
                    'Registrar a evolução dos indicadores no PGR e revisar periodicamente após mudanças organizacionais.',
                    'Promover ações preventivas leves nas dimensões com maior potencial de melhoria, mesmo dentro da faixa favorável.',
                ],
            ],
            'action_plan' => [
                'intro' => 'Plano de ação com foco exclusivo em medidas preventivas, alinhado ao cenário de baixo risco identificado na pesquisa.',
                'item_kind' => 'preventiva',
            ],
            'technical_referral' => [
                'heading' => 'Encaminhamento técnico',
                'body' => 'Informamos o resultado agregado desta campanha e encaminhamos para avaliação pela equipe de Saúde e Segurança do Trabalho (SST), para registro e acompanhamento no ciclo do PGR.',
                'conduct' => 'A equipe de SST deve validar os dados, confrontar com outras fontes internas e definir se há necessidade de medidas adicionais além das preventivas sugeridas.',
            ],
        ],
        'yellow' => [
            'label' => 'Atenção',
            'short_label' => 'Cenário geral: Atenção',
            'executive' => [
                'focus_heading' => 'Visão geral e pontos prioritários',
                'focus_intro' => 'O indicador geral requer atenção. Este relatório apresenta os pontos prioritários identificados na pesquisa e recomendações para mitigação dos riscos psicossociais.',
                'recommendations_heading' => 'Recomendações prioritárias',
                'recommendations' => [
                    'Priorizar as dimensões classificadas como risco intermediário ou elevado nas ações do plano.',
                    'Estabelecer acompanhamento obrigatório das medidas preventivas com responsáveis e prazos definidos.',
                    'Comunicar resultados à liderança e envolver trabalhadores no acompanhamento das ações.',
                ],
            ],
            'action_plan' => [
                'intro' => 'Plano de ação com medidas preventivas que exigem acompanhamento obrigatório, conforme cenário de atenção identificado na pesquisa.',
                'item_kind' => 'preventiva_com_acompanhamento',
            ],
            'technical_referral' => [
                'heading' => 'Encaminhamento técnico',
                'body' => 'Destacamos fatores identificados nesta campanha que podem exigir atualização do Programa de Gerenciamento de Riscos (PGR), conforme avaliação do responsável técnico.',
                'conduct' => 'O responsável técnico deve analisar os fatores de atenção, verificar aderência do inventário de riscos psicossociais e propor revisão do PGR quando necessário.',
            ],
        ],
        'red' => [
            'label' => 'Alto risco',
            'short_label' => 'Cenário geral: Alto risco',
            'executive' => [
                'focus_heading' => 'Visão geral e pontos críticos',
                'focus_intro' => 'O indicador geral aponta alto risco psicossocial. Este relatório destaca os pontos críticos e recomendações prioritárias para intervenção imediata.',
                'recommendations_heading' => 'Recomendações prioritárias',
                'recommendations' => [
                    'Acionar imediatamente a equipe de SST e a liderança para análise dos fatores críticos.',
                    'Implementar intervenções de curto prazo nas dimensões de maior risco, com prazos e responsáveis definidos.',
                    'Documentar medidas no PGR e avaliar necessidade de comunicação com CIPA e canais de apoio aos trabalhadores.',
                ],
            ],
            'action_plan' => [
                'intro' => 'Plano de ação com intervenções imediatas, alinhado ao cenário de alto risco identificado na pesquisa.',
                'item_kind' => 'intervencao_imediata',
            ],
            'technical_referral' => [
                'heading' => 'Encaminhamento técnico',
                'body' => 'Destacamos a existência de fatores de alto risco identificados nesta campanha e recomendamos análise prioritária pela empresa responsável pelo PGR.',
                'conduct' => 'A empresa responsável pelo PGR deve realizar análise prioritária, revisar controles existentes e definir plano de ação com prazos curtos para os fatores críticos.',
            ],
        ],
    ],

    'report_types' => [
        'executive' => 'Relatório executivo',
        'action_plan' => 'Plano de ação',
        'technical_referral' => 'Encaminhamento técnico',
    ],

];
