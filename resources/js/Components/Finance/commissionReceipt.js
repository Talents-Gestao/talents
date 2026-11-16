/**
 * Textos da tela de Comissões (pagamento antes do recebimento, adiantado e devolução).
 * Só apresentação: todos os valores vêm prontos do servidor (CommissionPayoutCalculator);
 * aqui nada é recalculado além de diferenças e do percentual recebido para exibição.
 */

export function formatCents(cents) {
    return (Number(cents ?? 0) / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

export function formatPercent(value) {
    return `${Number(value ?? 0).toLocaleString('pt-BR', { maximumFractionDigits: 2 })}%`;
}

/** Percentual da venda já recebido (uma casa decimal). */
export function receivedPercent(commission) {
    const total = Number(commission?.sale?.total_cents ?? 0);
    if (total < 1) return 0;

    return Math.round((Number(commission.sale.received_cents ?? 0) / total) * 1000) / 10;
}

/** Máximo que a usuária pode pagar agora: saldo (com a permissão de adiantar) ou liberável. */
export function paymentMaxCents(commission, canAdvance) {
    return Number(canAdvance ? commission?.payment_limit_cents : commission?.releasable_cents) || 0;
}

/** Parte do valor digitado acima do liberável (0 quando não há adiantamento). */
export function advancePartCents(commission, amountCents) {
    return Math.max(0, Number(amountCents || 0) - Number(commission?.releasable_cents ?? 0));
}

/** Texto da confirmação do adiantamento (o mesmo do servidor). */
export function advanceWarning(advanceCents) {
    return `Você está pagando ${formatCents(advanceCents)} acima do que o cliente já pagou. `
        + 'Se o cliente não pagar, esse valor deverá ser devolvido pela colaboradora.';
}

/** Valor digitado em reais («95,73» ou «95.73») → centavos; NaN se inválido. */
export function amountInputToCents(value) {
    const normalized = String(value ?? '').trim().replace(/\s/g, '').replace(',', '.');
    if (normalized === '' || Number.isNaN(Number(normalized))) return Number.NaN;

    return Math.round(Number(normalized) * 100);
}

/** Linhas sempre visíveis sob o status (adiantado, aguardando recebimento, devolução). */
export function rowNotes(commission) {
    const notes = [];
    const advance = Number(commission?.advance_cents ?? 0);
    if (advance > 0) {
        notes.push({ key: 'advance', tone: 'amber', text: `Adiantado: ${formatCents(advance)}, aguardando o cliente pagar` });
    }

    const remaining = Number(commission?.remaining_cents ?? 0);
    const releasable = Number(commission?.releasable_cents ?? 0);
    const outstanding = Number(commission?.sale?.outstanding_cents ?? 0);
    if (remaining > 0 && releasable < 1 && outstanding > 0) {
        notes.push({ key: 'awaiting', tone: 'slate', text: `Aguardando recebimento de ${formatCents(outstanding)}` });
    }

    const refund = Number(commission?.refund_pending_cents ?? 0);
    if (refund > 0) {
        notes.push({ key: 'refund', tone: 'rose', text: `Devolução pendente: ${formatCents(refund)}` });
    }

    return notes;
}

/** Motivo do botão «Registrar pagamento» bloqueado; null quando dá para pagar. */
export function paymentBlockedReason(commission, canAdvance = false) {
    if (!commission?.sale) return 'Venda não encontrada: não há valor a pagar.';

    const remaining = Number(commission.remaining_cents ?? 0);
    if (remaining < 1) return 'Comissão já paga integralmente.';
    if (canAdvance || Number(commission.releasable_cents ?? 0) >= 1) return null;

    const earned = Number(commission.earned_on_received_cents ?? 0);
    const paid = Number(commission.paid_cents ?? 0);
    const formula = `Liberável = ${formatPercent(commission.percent)} do que já foi recebido `
        + `(${formatCents(commission.sale.received_cents)}) = ${formatCents(earned)}`;

    let paidPart = '.';
    if (paid > 0 && earned === paid) {
        paidPart = ', valor já pago.';
    } else if (paid > earned) {
        paidPart = `; já foram pagos ${formatCents(paid)}.`;
    }

    return `${formula}${paidPart} O restante (${formatCents(remaining)}) é liberado conforme o cliente pagar. `
        + 'Pagar antes do recebimento exige a permissão «Pagar antes do recebimento».';
}

/** Resumo da linha expandida, na ordem exibida. */
export function commissionSummaryRows(commission) {
    const sale = commission?.sale;

    return [
        { key: 'sale_total', label: 'Total da venda', value: formatCents(sale?.total_cents) },
        { key: 'received', label: 'Recebido', value: formatCents(sale?.received_cents) },
        { key: 'received_percent', label: '% recebido', value: formatPercent(receivedPercent(commission)) },
        { key: 'ceiling', label: `Teto da comissão (${formatPercent(commission?.percent)})`, value: formatCents(commission?.amount_cents) },
        { key: 'paid', label: 'Pago', value: formatCents(commission?.paid_cents) },
        { key: 'releasable', label: 'Liberável', value: formatCents(commission?.releasable_cents) },
        { key: 'advance', label: 'Adiantado', value: formatCents(commission?.advance_cents) },
        { key: 'refund', label: 'Devolução pendente', value: formatCents(commission?.refund_pending_cents) },
    ];
}

/** Data AAAA-MM-DD → DD/MM/AAAA, sem conversão de fuso. */
export function formatDueDate(isoDate) {
    if (!isoDate) return '—';
    const [y, m, d] = String(isoDate).slice(0, 10).split('-');

    return `${d}/${m}/${y}`;
}
