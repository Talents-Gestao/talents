<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;

class RhidApiException extends Exception
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?array $payload = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function fromResponse(Response $response, string $context = 'RHID'): self
    {
        $json = $response->json();

        $message = is_array($json) && isset($json['error']) && is_string($json['error'])
            ? $json['error']
            : 'Erro na API RHID ('.$context.').';

        return new self(
            message: $message,
            httpStatus: $response->status(),
            payload: is_array($json) ? $json : null,
        );
    }
}
