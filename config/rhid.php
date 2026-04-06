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
    | Polling de relatório assíncrono
    |--------------------------------------------------------------------------
    */
    'report_poll_max_attempts' => (int) env('RHID_REPORT_POLL_MAX', 60),

    'report_poll_sleep_ms' => (int) env('RHID_REPORT_POLL_SLEEP_MS', 1000),

];
