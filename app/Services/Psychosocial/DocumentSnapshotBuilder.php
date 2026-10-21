<?php

declare(strict_types=1);

namespace App\Services\Psychosocial;

use App\Exceptions\PsychosocialDocumentException;
use App\Models\Survey;

interface DocumentSnapshotBuilder
{
    public function schema(): string;

    /**
     * @throws PsychosocialDocumentException quando a campanha não pode liberar este documento
     */
    public function assertReleasable(Survey $survey): void;

    /**
     * @return array<string, mixed>
     */
    public function build(Survey $survey): array;
}
