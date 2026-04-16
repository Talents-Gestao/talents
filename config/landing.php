<?php

/**
 * Landing pública (formulário de interesse, etc.).
 */
return [
    /**
     * Destinatários do aviso de novo interesse (separados por vírgula no .env).
     *
     * @var list<string>
     */
    'interest_recipients' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'LANDING_INTEREST_RECIPIENTS',
            'murilo@pasqualino.com.br,suzanepasqualino@gmail.com,murilo.contato9@gmail.com'
        ))
    ))),
];
