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
    case HiringFollowUpFromProposal = 'hiring_follow_up_from_proposal';
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

    // NR-1 V11: oportunidade (Comercial), solicitação de proposta ou conversa (Talents), relatório publicado (empresa)
    case Nr1OpportunityCreated = 'nr1_opportunity_created';
    case Nr1ProposalRequested = 'nr1_proposal_requested';
    case Nr1ReportPublished = 'nr1_report_published';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'novo item',
            self::Updated => 'item atualizado',
            self::Deleted => 'item removido',
            self::DateChanged => 'data alterada',
            self::ProposalCreated => 'nova proposta',
            self::ProposalWon => 'proposta fechada',
            self::HiringFollowUpFromProposal => 'acompanhamento criado',
            self::SaleCreated => 'nova venda',
            self::InstallmentPaid => 'parcela paga',
            self::InstallmentOverdue => 'parcela vencida',
            self::CommissionPaid => 'comissão paga',
            self::LeadReceived => 'novo lead',
            self::FeedbackAwaitingSignature => 'feedback enviado para assinatura',
            self::FeedbackCompleted => 'feedback concluído',
            self::ComplaintCreated => 'nova denúncia',
            self::ComplaintUpdated => 'denúncia atualizada',
            self::Nr1OpportunityCreated => 'nova oportunidade NR-1',
            self::Nr1ProposalRequested => 'nova solicitação de proposta ou conversa',
            self::Nr1ReportPublished => 'relatório publicado',
        };
    }
}
