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

];
