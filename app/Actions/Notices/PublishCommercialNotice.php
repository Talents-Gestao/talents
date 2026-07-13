<?php

declare(strict_types=1);

namespace App\Actions\Notices;

use App\Enums\CompanyNoticeAudience;
use App\Enums\CompanyNoticeEventKind;
use App\Models\CommercialCommission;
use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use App\Models\CommercialSaleInstallment;
use App\Models\User;

/**
 * Avisos internos da Talents para eventos comerciais/financeiros.
 * Todos com audiência Talents (sem empresa associada).
 */
class PublishCommercialNotice
{
    private const DEDUPE_MINUTES = 5;

    public function __construct(
        private readonly PublishCompanyNotice $publish,
    ) {}

    public function proposalCreated(CommercialProposal $proposal, ?User $actor = null): void
    {
        $this->talents(
            title: 'Nova proposta criada',
            body: "Proposta {$proposal->code} para «{$proposal->client_name}» no valor de "
                .$this->money($proposal->total_final_cents).'.',
            eventKind: CompanyNoticeEventKind::ProposalCreated,
            sourceId: (int) $proposal->id,
            actor: $actor,
        );
    }

    public function proposalWon(CommercialProposal $proposal, ?User $actor = null): void
    {
        $this->talents(
            title: 'Proposta fechada',
            body: "A proposta {$proposal->code} de «{$proposal->client_name}» foi marcada como fechada ("
                .$this->money($proposal->total_final_cents).'). Converta-a em venda no financeiro.',
            eventKind: CompanyNoticeEventKind::ProposalWon,
            sourceId: (int) $proposal->id,
            actor: $actor,
        );
    }

    public function saleCreated(CommercialSale $sale, ?User $actor = null): void
    {
        $this->talents(
            title: 'Nova venda registada',
            body: "Venda {$sale->code} de «{$sale->client_name}» no valor de "
                .$this->money($sale->total_cents)." em {$sale->installments_count}x.",
            eventKind: CompanyNoticeEventKind::SaleCreated,
            sourceId: (int) $sale->id,
            actor: $actor,
            sourceType: 'commercial_sale',
        );
    }

    public function installmentPaid(CommercialSaleInstallment $installment, ?User $actor = null): void
    {
        $sale = $installment->sale;

        $this->talents(
            title: 'Parcela recebida',
            body: "Parcela {$installment->number} da venda {$sale?->code} ("
                .($sale?->client_name ?? '—').') foi paga: '
                .$this->money($installment->paid_amount_cents ?? $installment->amount_cents).'.',
            eventKind: CompanyNoticeEventKind::InstallmentPaid,
            sourceId: (int) $installment->id,
            actor: $actor,
            sourceType: 'commercial_sale_installment',
        );
    }

    public function installmentOverdue(CommercialSaleInstallment $installment): void
    {
        $sale = $installment->sale;
        $due = $installment->due_date?->format('d/m/Y') ?? '—';

        $this->talents(
            title: 'Parcela vencida',
            body: "Parcela {$installment->number} da venda {$sale?->code} ("
                .($sale?->client_name ?? '—').") venceu em {$due}: "
                .$this->money($installment->amount_cents).'.',
            eventKind: CompanyNoticeEventKind::InstallmentOverdue,
            sourceId: (int) $installment->id,
            sourceType: 'commercial_sale_installment',
            // Uma única notificação de vencimento por parcela.
            dedupeWithinMinutes: null,
        );
    }

    public function commissionPaid(CommercialCommission $commission, ?User $actor = null): void
    {
        $sale = $commission->sale;
        $seller = $commission->seller;

        $this->talents(
            title: 'Comissão paga',
            body: "Comissão de «{$seller?->name}» na venda {$sale?->code} foi paga: "
                .$this->money($commission->amount_cents).'.',
            eventKind: CompanyNoticeEventKind::CommissionPaid,
            sourceId: (int) $commission->id,
            actor: $actor,
            sourceType: 'commercial_commission',
        );
    }

    private function talents(
        string $title,
        string $body,
        CompanyNoticeEventKind $eventKind,
        int $sourceId,
        ?User $actor = null,
        string $sourceType = 'commercial_proposal',
        ?int $dedupeWithinMinutes = self::DEDUPE_MINUTES,
    ): void {
        $this->publish->handle(
            companyId: null,
            title: $title,
            body: $body,
            audience: CompanyNoticeAudience::Talents,
            actor: $actor,
            sourceType: $sourceType,
            sourceId: $sourceId,
            eventKind: $eventKind,
            dedupeWithinMinutes: $dedupeWithinMinutes,
        );
    }

    private function money(?int $cents): string
    {
        return 'R$ '.number_format((int) $cents / 100, 2, ',', '.');
    }
}
