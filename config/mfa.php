<?php

/*
|--------------------------------------------------------------------------
| Verificação em duas etapas (MFA, TOTP)
|--------------------------------------------------------------------------
|
| - required_at_login: exige MFA no login de TODO super_admin (desligado por padrão; a inscrição é opcional).
| - step_up_required: ações sensíveis do NR-1 (publicar documentos, dado bruto, Revisão interna) pedem MFA recente.
| - Revisor externo: MFA SEMPRE obrigatório no login, não depende destas opções.
| Nada disto trava Financeiro, Comercial, RHID, Tarefas etc. para quem não se inscreveu.
*/

return [
    'issuer' => env('MFA_ISSUER', 'Talents'),

    'required_at_login' => (bool) env('MFA_REQUIRED_AT_LOGIN', false),

    'step_up_required' => (bool) env('MFA_STEP_UP_REQUIRED', true),

    // Quanto tempo uma verificação vale para as ações sensíveis (minutos).
    'step_up_minutes' => (int) env('MFA_STEP_UP_MINUTES', 15),

    // Tolerância do relógio: ±1 intervalo de 30 s.
    'window' => 1,

    'recovery_codes' => 10,

    // Tentativas erradas antes do bloqueio temporário (por usuário e por IP) e duração do bloqueio.
    'max_attempts' => (int) env('MFA_MAX_ATTEMPTS', 5),
    'lockout_minutes' => (int) env('MFA_LOCKOUT_MINUTES', 15),
];
