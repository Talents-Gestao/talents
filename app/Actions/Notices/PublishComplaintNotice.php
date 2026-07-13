<?php

declare(strict_types=1);

namespace App\Actions\Notices;

use App\Enums\CompanyNoticeAudience;
use App\Enums\CompanyNoticeEventKind;
use App\Models\Complaint;
use App\Models\User;

/**
 * Avisos por empresa para o canal de denúncias (Voz do Time).
 * Nunca inclui a descrição (dado sensível/encriptado) no corpo do aviso.
 */
class PublishComplaintNotice
{
    private const CATEGORIES = [
        'assedio_moral' => 'Assédio moral',
        'assedio_sexual' => 'Assédio sexual',
        'discriminacao' => 'Discriminação',
        'corrupcao' => 'Corrupção',
        'seguranca' => 'Segurança',
        'outros' => 'Outros',
    ];

    private const STATUSES = [
        'new' => 'Nova',
        'under_review' => 'Em análise',
        'resolved' => 'Resolvida',
        'archived' => 'Arquivada',
    ];

    public function __construct(
        private readonly PublishCompanyNotice $publish,
    ) {}

    public function created(Complaint $complaint): void
    {
        $category = self::CATEGORIES[$complaint->category] ?? 'denúncia';

        $this->publish->handle(
            companyId: (int) $complaint->company_id,
            title: 'Nova denúncia recebida',
            body: "Foi registada uma denúncia ({$category}). Protocolo {$complaint->protocol}. "
                .'Acesse o canal de denúncias para acompanhar.',
            audience: CompanyNoticeAudience::Company,
            sourceType: 'complaint',
            sourceId: (int) $complaint->id,
            eventKind: CompanyNoticeEventKind::ComplaintCreated,
            dedupeWithinMinutes: 5,
        );
    }

    public function statusUpdated(Complaint $complaint, string $previous, string $current, ?User $actor = null): void
    {
        $from = self::STATUSES[$previous] ?? $previous;
        $to = self::STATUSES[$current] ?? $current;

        $this->publish->handle(
            companyId: (int) $complaint->company_id,
            title: 'Denúncia atualizada',
            body: "A denúncia de protocolo {$complaint->protocol} mudou de «{$from}» para «{$to}».",
            audience: CompanyNoticeAudience::Company,
            actor: $actor,
            sourceType: 'complaint',
            sourceId: (int) $complaint->id,
            eventKind: CompanyNoticeEventKind::ComplaintUpdated,
            dedupeWithinMinutes: 1,
        );
    }
}
