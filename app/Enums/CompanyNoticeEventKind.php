<?php

namespace App\Enums;

enum CompanyNoticeEventKind: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case DateChanged = 'date_changed';

    // Comercial / Financeiro (audiência Talents)
    case ProposalCreated = 'proposal_created';
    case ProposalWon = 'proposal_won';
    case SaleCreated = 'sale_created';
    case InstallmentPaid = 'installment_paid';
    case InstallmentOverdue = 'installment_overdue';
    case CommissionPaid = 'commission_paid';
    case LeadReceived = 'lead_received';

    // Feedbacks / Denúncias (audiência empresa)
    case FeedbackAwaitingSignature = 'feedback_awaiting_signature';
    case FeedbackCompleted = 'feedback_completed';
    case ComplaintCreated = 'complaint_created';
    case ComplaintUpdated = 'complaint_updated';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'novo item',
            self::Updated => 'item atualizado',
            self::Deleted => 'item removido',
            self::DateChanged => 'data alterada',
            self::ProposalCreated => 'nova proposta',
            self::ProposalWon => 'proposta fechada',
            self::SaleCreated => 'nova venda',
            self::InstallmentPaid => 'parcela paga',
            self::InstallmentOverdue => 'parcela vencida',
            self::CommissionPaid => 'comissão paga',
            self::LeadReceived => 'novo lead',
            self::FeedbackAwaitingSignature => 'feedback enviado para assinatura',
            self::FeedbackCompleted => 'feedback concluído',
            self::ComplaintCreated => 'nova denúncia',
            self::ComplaintUpdated => 'denúncia atualizada',
        };
    }
}
