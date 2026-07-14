<?php

return [

    /*
    |--------------------------------------------------------------------------
    | URL base da API RHID (Control iD)
    |--------------------------------------------------------------------------
    |
    | Pode ser sobrescrita por empresa (campo rhid_base_url em companies).
    |
    */
    'base_url' => env('RHID_BASE_URL', 'https://www.rhid.com.br/v2'),

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('RHID_HTTP_TIMEOUT', 60),

    'report_timeout' => (int) env('RHID_REPORT_TIMEOUT', 180),

    /*
    |--------------------------------------------------------------------------
    | save_file (PDF/HTML após GUID em 100%)
    |--------------------------------------------------------------------------
    |
    | Alguns tenants devolvem 200 com corpo vazio se o arquivo ainda não está
    | pronto ou sob carga. Aumente se o lote "Salvar todos" falhar no 2º+ item.
    |
    */
    'save_file_max_attempts_per_format' => (int) env('RHID_SAVE_FILE_MAX_ATTEMPTS', 12),

    'save_file_retry_base_ms' => (int) env('RHID_SAVE_FILE_RETRY_BASE_MS', 400),

    /*
    |--------------------------------------------------------------------------
    | Cache do token (login retorna ~4h; renovamos antes)
    |--------------------------------------------------------------------------
    */
    'token_cache_ttl' => (int) env('RHID_TOKEN_CACHE_TTL', 12600),

    /*
    |--------------------------------------------------------------------------
    | Departamentos (customerdb)
    |--------------------------------------------------------------------------
    |
    | Conforme documentacao da API Control iD (apicontrolid / RHiD), a listagem
    | de departamentos segue o mesmo padrao de cadastro: GET com paginacao em
    | customerdb/department.svc/a (page, maxSize). Alguns tenants usam o path
    | alternativo departament.svc/a — o codigo tenta ambos.
    |
    | Cargos (funcoes): GET customerdb/personrole.svc/a com os mesmos parametros;
    | fallbacks: personroles.svc, person_role.svc.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Banco de horas (person_banco_horas)
    |--------------------------------------------------------------------------
    |
    | Por padrao usamos uma unica chamada GET com ?date= conforme a API RHID.
    | Defina true apenas se o seu tenant RHID exigir agregar por lista de IDs
    | (varias requisicoes — mais lento e sujeito a timeout).
    |
    */
    'bank_hours_aggregate' => filter_var(env('RHID_BANK_HOURS_AGGREGATE', false), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | Polling de relatório assíncrono
    |--------------------------------------------------------------------------
    */
    'report_poll_max_attempts' => (int) env('RHID_REPORT_POLL_MAX', 60),

    'report_poll_sleep_ms' => (int) env('RHID_REPORT_POLL_SLEEP_MS', 1000),

    /*
    |--------------------------------------------------------------------------
    | Parser Python do espelho (PDF salvo localmente)
    |--------------------------------------------------------------------------
    */
    /*
     * Em Linux/Docker use caminho absoluto se o PHP-FPM tiver PATH minimo (Coolify).
     * Sobrescreva com RHID_ESPELHO_PYTHON=/usr/bin/python3.12 se necessario.
     */
    'espelho_python' => (function () {
        $v = env('RHID_ESPELHO_PYTHON');
        if (is_string($v) && $v !== '') {
            return $v;
        }

        return PHP_OS_FAMILY === 'Windows' ? 'python' : '/usr/bin/python3';
    })(),

    'espelho_parser_workdir' => env(
        'RHID_ESPELHO_PARSER_WORKDIR',
        base_path('tools/rhid-espelho-parser'),
    ),

    'espelho_parse_timeout_seconds' => (int) env('RHID_ESPELHO_PARSE_TIMEOUT', 120),

    /*
    |--------------------------------------------------------------------------
    | Auditoria local do merge person (banco de horas / detalhe colaborador)
    |--------------------------------------------------------------------------
    |
    | Em APP_ENV=local, o servico registra no log (debug) JSON antes/depois do
    | merge do objeto aninhado person. Por padrao so grava quando o snapshot
    | compacto muda; defina RHID_MERGE_AUDIT_FULL=true para linha completa em
    | todas as linhas (pode gerar logs grandes).
    |
    */
    'merge_audit_full' => filter_var(env('RHID_MERGE_AUDIT_FULL', false), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | Listagem de justificativas (POST justification.svc/list)
    |--------------------------------------------------------------------------
    |
    | Formato de ini/fim no corpo enviado ao RHID (apos validar yyyyMMdd no Talents):
    | - iso: yyyy-MM-dd (padrao; costuma funcionar com DateTime .NET / JSON)
    | - compact: yyyyMMdd (documentacao Control iD "AnoMesDia")
    | - br: dd/MM/yyyy (cultura pt-BR em alguns tenants)
    |
    | RHID_JUSTIFICATION_LIST_INI_FIM_FORMAT sobrescreve tudo. Se nao definido,
    | RHID_JUSTIFICATION_LIST_DATES_BR=true ainda forca modo br (compatibilidade).
    |
    */
    'justification_list_ini_fim_format' => (function () {
        $fmt = env('RHID_JUSTIFICATION_LIST_INI_FIM_FORMAT');
        if (is_string($fmt) && $fmt !== '') {
            return strtolower($fmt);
        }
        if (filter_var(env('RHID_JUSTIFICATION_LIST_DATES_BR', false), FILTER_VALIDATE_BOOL)) {
            return 'br';
        }

        return 'iso';
    })(),

    /*
    |--------------------------------------------------------------------------
    | POST justification.svc/list — payload extra (evita NullReference em alguns tenants)
    |--------------------------------------------------------------------------
    |
    | draw: padrao 0 (padrao DataTables usado pelo painel RHID).
    |
    | default_company_id: quando definido (ex. ID da empresa no cadastro RHID) e o cliente
    | nao envia "companies", injeta [id] — alguns servidores exigem companies nao-nulo.
    |
    */
    'justification_list_draw' => (int) env('RHID_JUSTIFICATION_LIST_DRAW', 0),

    'justification_list_default_company_id' => (function () {
        $v = env('RHID_JUSTIFICATION_LIST_DEFAULT_COMPANY_ID');
        if ($v === null || $v === '') {
            return null;
        }

        return (int) $v;
    })(),

    /*
    |--------------------------------------------------------------------------
    | Horários da empresa (aderência espelho vs escala)
    |--------------------------------------------------------------------------
    |
    | Valor usado quando a empresa ainda não gravou tolerancia_minutos em
    | company_rhid_schedule_settings. A aderência sempre lê o valor salvo na
    | configuração RHID quando existir; este é apenas o fallback.
    |
    */
    'default_schedule_tolerance_minutes' => (int) env('RHID_DEFAULT_SCHEDULE_TOLERANCE_MINUTES', 10),

    /*
    |--------------------------------------------------------------------------
    | Colaboradores fictícios (teste / local)
    |--------------------------------------------------------------------------
    |
    | Quando a empresa não tem RHID configurado, devolve nomes de exemplo para
    | selects de Feedbacks, Férias e Desligamento. Ativo em APP_ENV=local ou
    | com RHID_DEMO_PERSONS=true. Nunca substitui a API se o RHID estiver ok.
    |
    */
    'demo_persons' => filter_var(env('RHID_DEMO_PERSONS', false), FILTER_VALIDATE_BOOL)
        || env('APP_ENV') === 'local',

];
