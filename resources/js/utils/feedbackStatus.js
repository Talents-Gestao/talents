const STATUS_MAP = {
    draft: {
        label: 'Rascunho',
        badge: 'bg-slate-100 text-slate-700 ring-slate-200',
    },
    in_progress: {
        label: 'Em preenchimento',
        badge: 'bg-sky-50 text-sky-800 ring-sky-200',
    },
    awaiting_signatures: {
        label: 'Aguardando assinaturas',
        badge: 'bg-amber-50 text-amber-800 ring-amber-200',
    },
    completed: {
        label: 'Concluído',
        badge: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
    },
    cancelled: {
        label: 'Cancelado',
        badge: 'bg-rose-50 text-rose-800 ring-rose-200',
    },
};

export function feedbackStatusLabel(status) {
    return STATUS_MAP[status]?.label ?? status ?? '—';
}

export function feedbackStatusBadgeClass(status) {
    return STATUS_MAP[status]?.badge ?? 'bg-slate-100 text-slate-600 ring-slate-200';
}

export const feedbackFieldClass =
    'mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/60';

export const feedbackCardClass =
    'overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm';
