<?php

/**
 * Limites por IP + token de rota pública (anti-spam / abuso).
 * Chaves usadas em AppServiceProvider (RateLimiter::for).
 */
return [
    /** POST /pesquisa/{token} */
    'survey_submit_per_minute' => 30,

    /** POST /denuncia/{token} (nova denúncia) */
    'complaint_store_per_minute' => 10,

    /** POST /denuncia/{token}/acompanhar */
    'complaint_track_lookup_per_minute' => 20,

    /** POST /denuncia/{token}/p/{protocol}/mensagem */
    'complaint_reporter_message_per_minute' => 30,
];
