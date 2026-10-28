<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Metodologia NR-1 / COPSOQ
    |--------------------------------------------------------------------------
    |
    | Média ponderada das respostas Likert 1–5 (com inversão por item quando
    | reverse_score=true). Quanto maior a média, maior o risco psicossocial.
    | Tercis: leve ≤ 2,33 · moderado ≤ 3,66 · grave > 3,66.
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
        'green' => 'Leve',
        'yellow' => 'Moderado',
        'red' => 'Grave',
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

    /*
    |--------------------------------------------------------------------------
    | Coleta pública (questionário do colaborador)
    |--------------------------------------------------------------------------
    |
    | Padrões conservadores: nada de novo é coletado. A política de envio duplicado e o rascunho
    | dependem de decisão da responsável técnica e do jurídico/LGPD (ver docs de decisões pendentes).
    |
    | duplicate_policy:
    |   none          — só limite de taxa (comportamento atual; nenhum dado novo)
    |   device_cookie — marca o navegador após o envio; sem identificador no servidor (burlável)
    |   one_time_token — convite de uso único (nr1:generate-invites); só o hash do código é guardado
    |
    | client_draft: rascunho só no navegador do respondente (localStorage); nada vai ao servidor.
    */
    'collection' => [
        'duplicate_policy' => env('NR1_DUPLICATE_POLICY', 'none'),
        'client_draft' => (bool) env('NR1_CLIENT_DRAFT', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Revisão externa de documentos (organização aprovadora)
    |--------------------------------------------------------------------------
    |
    | DESLIGADO por padrão. Só ligar em produção depois do teste manual do MFA com um aplicativo
    | autenticador real e do OK do responsável (ver docs/nr1-v11/NOTAS.md).
    | Desligado: compartilhar é recusado, o revisor recebe 404 e nenhum compartilhamento vale.
    |
    | mfa_required: com a revisão ligada e este flag true, o middleware EnsureExternalReviewer exige
    | o MFA (TOTP) verificado na sessão (marca mfa_verified_at); sem ele o revisor vai para o desafio
    | ou para a inscrição.
    */
    'external_review' => [
        'enabled' => (bool) env('NR1_EXTERNAL_REVIEW_ENABLED', false),
        'mfa_required' => (bool) env('NR1_EXTERNAL_REVIEW_MFA_REQUIRED', true),
        'max_share_days' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Shell NR-1 (navegação própria em /nr1)
    |--------------------------------------------------------------------------
    |
    | Desligado por padrão em produção e ligado no ambiente local. Ligado: aparece UM item «NR-1» no menu
    | principal (e some o item antigo «Pesquisas NR1» da empresa) e as rotas /nr1/* respondem. Desligado: o
    | sistema se comporta exatamente como antes e /nr1/* responde 404. As rotas antigas (admin e client)
    | nunca saem do ar: continuam valendo por URL direta nos dois casos. Ligar em produção só depois da
    | aprovação do visual pela Suzane.
    */
    'shell_enabled' => (bool) env('NR1_SHELL_ENABLED', env('APP_ENV') === 'local'),

    /*
    | Laudo do modelo anterior DENTRO do shell (Etapa L). Desligado (padrão em TODOS os ambientes): as pesquisas do
    | modelo anterior mantêm «Parecer e plano» e a tela antiga; o shell só mostra o aviso «Pesquisa no modelo anterior» com
    | o botão «Abrir Laudo NR-1». Ligado: Resultados, Documentos e Visão geral do legado são renderizados no shell
    | (Components/Nr1Legacy, tema .nr1-theme). A decisão de como o Laudo entra no fluxo está pendente (ver NOTAS.md).
    */
    'shell_legacy_embed' => (bool) env('NR1_SHELL_LEGACY_EMBED', false),

    /*
    |--------------------------------------------------------------------------
    | Soluções Talents (Caderno §14)
    |--------------------------------------------------------------------------
    |
    | require_review: a empresa só vê uma regra «dimensão → serviço» depois que a Talents revisa o texto. Os textos
    | iniciais vêm do HTML aprovado e a aprovação deles é a pergunta 11 da Suzane (ver docs/nr1-v11/NOTAS.md).
    */
    'solutions' => [
        'require_review' => (bool) env('NR1_SOLUTIONS_REQUIRE_REVIEW', true),
    ],

    'benchmark_alert_margin' => 0.20,

    'trend_change_threshold' => 0.25,

];
