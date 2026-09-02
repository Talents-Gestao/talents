/**
 * Estado de assinatura do contrato na listagem/kanban de propostas.
 *
 * @param {{ contract_signed?: boolean, zapsign_pending?: boolean } | null | undefined} proposal
 * @returns {{
 *   key: 'signed' | 'pending' | 'unsigned',
 *   label: string,
 *   className: string,
 *   title: string,
 * }}
 */
export function proposalContractSignature(proposal) {
    if (proposal?.contract_signed) {
        return {
            key: 'signed',
            label: 'Assinado',
            className: 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200/80',
            title: 'Há contrato assinado no ZapSign',
        };
    }

    if (proposal?.zapsign_pending) {
        return {
            key: 'pending',
            label: 'Aguardando assinatura',
            className: 'bg-sky-50 text-sky-900 ring-1 ring-sky-200/80',
            title: 'Contrato enviado no ZapSign, aguardando assinatura',
        };
    }

    return {
        key: 'unsigned',
        label: 'Não assinado',
        className: 'bg-slate-100 text-slate-600 ring-1 ring-slate-200/80',
        title: 'Ainda não há contrato assinado',
    };
}
