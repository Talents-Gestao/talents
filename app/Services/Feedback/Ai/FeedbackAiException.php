<?php

declare(strict_types=1);

namespace App\Services\Feedback\Ai;

use RuntimeException;

/**
 * Falha esperada no uso de IA em Feedbacks. A mensagem é a que o usuário vê (português, sem detalhe técnico);
 * o código vai para ai_usage_logs e o detalhe técnico só para o log da aplicação.
 */
class FeedbackAiException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode = 'error')
    {
        parent::__construct($message);
    }
}
