<?php

declare(strict_types=1);

namespace App\Services\Feedback\Ai;

/** Falha depois de o provedor já ter cobrado tokens (ex.: resposta cortada): o uso entra no log mesmo assim. */
class FeedbackAiUsageException extends FeedbackAiException
{
    public function __construct(string $message, string $errorCode, public readonly int $inputTokens, public readonly int $outputTokens)
    {
        parent::__construct($message, $errorCode);
    }
}
