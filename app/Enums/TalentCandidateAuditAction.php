<?php

declare(strict_types=1);

namespace App\Enums;

enum TalentCandidateAuditAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Archived = 'archived';
    case Restored = 'restored';
    case ImportedSolides = 'imported_solides';
    case AttachmentUploaded = 'attachment_uploaded';
    case AttachmentDownloaded = 'attachment_downloaded';
    case AttachmentDeleted = 'attachment_deleted';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Cadastro criado',
            self::Updated => 'Cadastro alterado',
            self::Archived => 'Cadastro arquivado',
            self::Restored => 'Cadastro restaurado',
            self::ImportedSolides => 'Importado da Sólides',
            self::AttachmentUploaded => 'Anexo interno enviado',
            self::AttachmentDownloaded => 'Anexo interno baixado',
            self::AttachmentDeleted => 'Anexo interno excluído',
        };
    }

    /** Ações de anexo: detalhes só para quem tem contratacao_anexos. */
    public function isAttachment(): bool
    {
        return in_array($this, [
            self::AttachmentUploaded,
            self::AttachmentDownloaded,
            self::AttachmentDeleted,
        ], true);
    }
}
