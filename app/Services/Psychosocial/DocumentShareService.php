<?php

declare(strict_types=1);

namespace App\Services\Psychosocial;

use App\Enums\UserRole;
use App\Exceptions\PsychosocialDocumentException;
use App\Models\DocumentShare;
use App\Models\DocumentShareReview;
use App\Models\PsychosocialDocumentVersion;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Compartilhamento de versões com revisor externo. Tudo aqui exige a revisão externa ligada
 * (config nr1.external_review.enabled, desligada por padrão até o MFA existir).
 */
class DocumentShareService
{
    public static function enabled(): bool
    {
        return (bool) config('nr1.external_review.enabled', false);
    }

    public function share(PsychosocialDocumentVersion $version, User $reviewer, User $sharedBy, CarbonInterface $expiresAt): DocumentShare
    {
        $this->assertEnabled();

        $maxDays = (int) config('nr1.external_review.max_share_days', 90);
        if ($expiresAt->gt(now()->addDays($maxDays))) {
            throw new PsychosocialDocumentException("A validade do compartilhamento pode ser de no máximo {$maxDays} dias.");
        }

        $organizationId = $reviewer->workspaces()
            ->where('is_active', true)
            ->where('role', UserRole::ExternalReviewer)
            ->value('approver_organization_id');

        if ($organizationId === null) {
            throw new PsychosocialDocumentException('O destinatário não é revisor externo ativo.');
        }

        $document = $version->document;

        return DB::transaction(function () use ($version, $reviewer, $sharedBy, $expiresAt, $organizationId, $document): DocumentShare {
            $existing = DocumentShare::query()
                ->where('document_version_id', $version->id)
                ->where('reviewer_id', $reviewer->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw new PsychosocialDocumentException(
                    $existing->isActive()
                        ? 'Esta versão já está compartilhada com este revisor.'
                        : 'Esta versão já foi compartilhada com este revisor e o acesso foi encerrado.'
                );
            }

            return DocumentShare::query()->create([
                'company_id' => $document->company_id,
                'survey_id' => $document->survey_id,
                'document_version_id' => $version->id,
                'reviewer_id' => $reviewer->id,
                'approver_organization_id' => $organizationId,
                'shared_by' => $sharedBy->id,
                'shared_at' => now(),
                'expires_at' => $expiresAt,
            ]);
        });
    }

    public function revoke(DocumentShare $share): void
    {
        if ($share->revoked_at === null) {
            $share->update(['revoked_at' => now()]);
        }
    }

    public function comment(DocumentShare $share, User $reviewer, string $body): DocumentShareReview
    {
        return DocumentShareReview::query()->create([
            'document_share_id' => $share->id,
            'user_id' => $reviewer->id,
            'kind' => DocumentShareReview::KIND_COMMENT,
            'body' => trim($body),
        ]);
    }

    public function approve(DocumentShare $share, User $reviewer): DocumentShareReview
    {
        return DB::transaction(function () use ($share, $reviewer): DocumentShareReview {
            DocumentShare::query()->whereKey($share->id)->lockForUpdate()->first();

            if ($share->reviews()->where('kind', DocumentShareReview::KIND_APPROVAL)->exists()) {
                throw new PsychosocialDocumentException('Esta versão já foi aprovada por este revisor.');
            }

            return DocumentShareReview::query()->create([
                'document_share_id' => $share->id,
                'user_id' => $reviewer->id,
                'kind' => DocumentShareReview::KIND_APPROVAL,
                'body' => null,
            ]);
        });
    }

    private function assertEnabled(): void
    {
        if (! self::enabled()) {
            throw new PsychosocialDocumentException('A revisão externa de documentos está desligada.');
        }
    }
}
