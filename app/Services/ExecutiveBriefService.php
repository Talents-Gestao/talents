<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PsychosocialDocument;
use App\Models\PsychosocialDocumentVersion;
use App\Models\Survey;
use App\Models\User;
use App\Services\Psychosocial\PsychosocialDocumentService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Atalho do resumo executivo sobre o ciclo de vida genérico dos documentos novos.
 */
class ExecutiveBriefService
{
    public const DISK = PsychosocialDocumentService::DISK;

    public const VIEW = 'psychosocial.executive_brief';

    private const TYPE = PsychosocialDocument::TYPE_EXECUTIVE_BRIEF;

    public function __construct(private readonly PsychosocialDocumentService $documents)
    {
    }

    public function release(Survey $survey): PsychosocialDocumentVersion
    {
        return $this->documents->release($survey, self::TYPE);
    }

    public function currentVersion(Survey $survey): ?PsychosocialDocumentVersion
    {
        return $this->documents->currentVersion($survey, self::TYPE);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function currentVersionSummary(Survey $survey): ?array
    {
        // Campanha V11: o resumo executivo é um dos dois documentos do fluxo de revisão (Central de documentos).
        if ($survey->usesReviewFlow()) {
            return null;
        }

        return $this->documents->currentVersionSummary($survey, self::TYPE);
    }

    public function download(PsychosocialDocumentVersion $version, User $user, ?string $ip): StreamedResponse
    {
        return $this->documents->download($version, $user, $ip);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>|null  $validationBlock
     */
    public function renderHtml(array $snapshot, ?array $validationBlock = null): string
    {
        return $this->documents->renderHtml(self::TYPE, $snapshot, $validationBlock);
    }
}
