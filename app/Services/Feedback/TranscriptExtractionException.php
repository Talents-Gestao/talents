<?php

declare(strict_types=1);

namespace App\Services\Feedback;

use RuntimeException;

/**
 * Falha ao ler a transcrição enviada. A mensagem é mostrada ao usuário (português, sem detalhe técnico).
 */
class TranscriptExtractionException extends RuntimeException {}
