<?php

/**
 * Landing pública (formulário de interesse, etc.).
 */

/** @var list<string> */
$defaultInterestRecipients = [
    'murilo@pasqualino.com.br',
    'suzanepasqualino@gmail.com',
    'murilo.contato9@gmail.com',
];

// env('X', $default) NÃO usa o default quando X existe no .env como string vazia — isso zerava a lista.
$raw = env('LANDING_INTEREST_RECIPIENTS');
if (! is_string($raw) || trim($raw) === '') {
    $parsed = $defaultInterestRecipients;
} else {
    $parsed = array_values(array_filter(array_map('trim', explode(',', $raw))));
}

return [
    /**
     * Destinatários do aviso de novo interesse (separados por vírgula no .env).
     *
     * @var list<string>
     */
    'interest_recipients' => $parsed !== [] ? $parsed : $defaultInterestRecipients,

    /**
     * Hero "Designed in glass" (WebGL). Desligado por padrão fora do ambiente local;
     * com false a landing renderiza o Hero atual, inalterado.
     */
    'hero_glass' => filter_var(env('LANDING_HERO_GLASS', env('APP_ENV') === 'local'), FILTER_VALIDATE_BOOLEAN),
];
