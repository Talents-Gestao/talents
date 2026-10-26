<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\PsychosocialDocumentVersion;
use App\Models\PsychosocialReviewRound;
use App\Services\Psychosocial\PsychosocialDocumentService;

/**
 * Forma única de mostrar uma rodada de revisão (Talents, empresa e revisor externo).
 */
final class ReviewRoundPresenter
{
    /**
     * @param  callable(PsychosocialDocumentVersion): ?string  $downloadUrl
     * @return list<array<string, mixed>>
     */
    public static function versions(PsychosocialReviewRound $round, callable $downloadUrl): array
    {
        $rows = [];

        foreach ([$round->executiveVersion, $round->technicalVersion] as $version) {
            if ($version === null) {
                continue;
            }

            $type = $version->document->type;
            $rows[] = [
                'id' => $version->id,
                'type' => $type,
                'label' => PsychosocialDocumentService::label($type),
                'version_number' => $version->version_number,
                'public_identifier' => $version->public_identifier,
                'pdf_status' => $version->pdf_status,
                'pdf_ready' => $version->pdf_status === PsychosocialDocumentVersion::PDF_READY,
                'pdf_sha256' => $version->pdf_sha256,
                'content_sha256' => $version->content_sha256,
                'generated_at' => $version->created_at?->format('d/m/Y H:i'),
                'download_url' => $version->pdf_status === PsychosocialDocumentVersion::PDF_READY ? $downloadUrl($version) : null,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public static function base(PsychosocialReviewRound $round): array
    {
        $round->loadMissing(['sentByUser:id,name', 'approvedByUser:id,name', 'publishedByUser:id,name', 'acknowledgement.user:id,name', 'pgrIntegration.user:id,name']);

        return [
            'id' => $round->id,
            'number' => $round->round_number,
            'status' => $round->status,
            'status_label' => $round->statusLabel(),
            'sent_at' => $round->sent_at?->format('d/m/Y H:i'),
            'sent_by' => $round->sentByUser?->name,
            'approved_at' => $round->approved_at?->format('d/m/Y H:i'),
            'approved_by' => $round->approvedByUser?->name,
            'published_at' => $round->published_at?->format('d/m/Y H:i'),
            'published_by' => $round->publishedByUser?->name,
            'protocol' => $round->protocol,
            'acknowledgement' => $round->acknowledgement ? [
                'by' => $round->acknowledgement->user?->name,
                'at' => $round->acknowledgement->acknowledged_at->format('d/m/Y H:i'),
                'protocol' => $round->acknowledgement->protocol,
            ] : null,
            'pgr_integration' => $round->pgrIntegration ? [
                'by' => $round->pgrIntegration->user?->name,
                'at' => $round->pgrIntegration->confirmed_at->format('d/m/Y H:i'),
                'file_name' => $round->pgrIntegration->evidence_name,
                'note' => $round->pgrIntegration->note,
            ] : null,
        ];
    }
}
