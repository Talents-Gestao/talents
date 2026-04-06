<?php

namespace App\Exceptions;

/**
 * Login retornou múltiplos clientes/dominios — o usuário deve informar rhid_domain.
 */
class RhidDomainChoiceRequiredException extends RhidApiException
{
    /**
     * @param  list<array<string, mixed>>  $listCustomer
     */
    public function __construct(
        public readonly array $listCustomer,
    ) {
        parent::__construct(
            message: 'Mais de um cliente/dominio no RHID. Informe o dominio nas configuracoes.',
            httpStatus: 400,
            payload: ['listCustomer' => $listCustomer],
        );
    }
}
