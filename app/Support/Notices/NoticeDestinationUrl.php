<?php

declare(strict_types=1);

namespace App\Support\Notices;

use App\Enums\CompanyNoticeEventKind;
use App\Models\CommercialCommission;
use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use App\Models\CommercialSaleInstallment;
use App\Models\CompanyNotice;
use App\Models\Complaint;
use App\Models\FeedbackSession;
use App\Models\StrategicCalendarItem;

/**
 * Destino da página ao abrir um aviso no sino (admin ou cliente).
 */
class NoticeDestinationUrl
{
    public function url(CompanyNotice $notice, bool $admin): ?string
    {
        $kind = $notice->event_kind;
        $sourceId = (int) ($notice->source_id ?? 0);

        if ($kind === null && $sourceId < 1) {
            return null;
        }

        return $admin
            ? $this->adminUrl($kind, $sourceId)
            : $this->clientUrl($kind, $sourceId);
    }

    private function adminUrl(?CompanyNoticeEventKind $kind, int $sourceId): ?string
    {
        return match ($kind) {
            CompanyNoticeEventKind::ProposalCreated,
            CompanyNoticeEventKind::ProposalWon => $this->proposalUrl($sourceId),
            CompanyNoticeEventKind::SaleCreated => $this->saleUrl($sourceId),
            CompanyNoticeEventKind::InstallmentPaid,
            CompanyNoticeEventKind::InstallmentOverdue => $this->saleUrlFromInstallment($sourceId),
            CompanyNoticeEventKind::CommissionPaid => $this->saleUrlFromCommission($sourceId),
            CompanyNoticeEventKind::LeadReceived => route('admin.landing-interest.index'),
            CompanyNoticeEventKind::Created,
            CompanyNoticeEventKind::Updated,
            CompanyNoticeEventKind::DateChanged => $this->adminCalendarUrl($sourceId),
            CompanyNoticeEventKind::Deleted => route('admin.strategic-calendar.index'),
            CompanyNoticeEventKind::FeedbackAwaitingSignature,
            CompanyNoticeEventKind::FeedbackCompleted => $this->adminFeedbackUrl($sourceId),
            CompanyNoticeEventKind::ComplaintCreated,
            CompanyNoticeEventKind::ComplaintUpdated => $this->adminComplaintUrl($sourceId),
            default => null,
        };
    }

    private function clientUrl(?CompanyNoticeEventKind $kind, int $sourceId): ?string
    {
        return match ($kind) {
            CompanyNoticeEventKind::Created,
            CompanyNoticeEventKind::Updated,
            CompanyNoticeEventKind::DateChanged,
            CompanyNoticeEventKind::Deleted => route('client.strategic-calendar.index'),
            CompanyNoticeEventKind::FeedbackAwaitingSignature,
            CompanyNoticeEventKind::FeedbackCompleted => $this->clientFeedbackUrl($sourceId),
            CompanyNoticeEventKind::ComplaintCreated,
            CompanyNoticeEventKind::ComplaintUpdated => $this->clientComplaintUrl($sourceId),
            default => null,
        };
    }

    private function proposalUrl(int $sourceId): string
    {
        if ($sourceId > 0 && CommercialProposal::query()->whereKey($sourceId)->exists()) {
            return route('admin.comercial.propostas.edit', $sourceId);
        }

        return route('admin.comercial.propostas.index');
    }

    private function saleUrl(int $sourceId): string
    {
        if ($sourceId > 0 && CommercialSale::query()->whereKey($sourceId)->exists()) {
            return route('admin.financeiro.vendas.show', $sourceId);
        }

        return route('admin.financeiro.vendas.index');
    }

    private function saleUrlFromInstallment(int $installmentId): string
    {
        $saleId = (int) CommercialSaleInstallment::query()->whereKey($installmentId)->value('sale_id');

        return $this->saleUrl($saleId);
    }

    private function saleUrlFromCommission(int $commissionId): string
    {
        $saleId = (int) CommercialCommission::query()->whereKey($commissionId)->value('sale_id');

        return $this->saleUrl($saleId);
    }

    private function adminCalendarUrl(int $sourceId): string
    {
        if ($sourceId > 0 && StrategicCalendarItem::query()->whereKey($sourceId)->exists()) {
            return route('admin.strategic-calendar.edit', $sourceId);
        }

        return route('admin.strategic-calendar.index');
    }

    private function adminFeedbackUrl(int $sourceId): string
    {
        if ($sourceId > 0 && FeedbackSession::query()->whereKey($sourceId)->exists()) {
            return route('admin.feedbacks.sessions.show', $sourceId);
        }

        return route('admin.feedbacks.index');
    }

    private function adminComplaintUrl(int $sourceId): string
    {
        if ($sourceId > 0 && Complaint::query()->whereKey($sourceId)->exists()) {
            return route('admin.complaints.show', $sourceId);
        }

        return route('admin.complaints.index');
    }

    private function clientFeedbackUrl(int $sourceId): string
    {
        if ($sourceId > 0 && FeedbackSession::query()->whereKey($sourceId)->exists()) {
            return route('client.feedbacks.sessions.show', $sourceId);
        }

        return route('client.feedbacks.index');
    }

    private function clientComplaintUrl(int $sourceId): string
    {
        if ($sourceId > 0 && Complaint::query()->whereKey($sourceId)->exists()) {
            return route('client.complaints.show', $sourceId);
        }

        return route('client.complaints.index');
    }
}
