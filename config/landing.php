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
];
