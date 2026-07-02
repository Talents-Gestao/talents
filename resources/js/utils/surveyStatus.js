const SURVEY_STATUS_LABELS = {
    active: 'Ativa',
    draft: 'Rascunho',
    closed: 'Encerrada',
};

const SURVEY_STATUS_BADGE_CLASSES = {
    active: 'bg-emerald-100 text-emerald-800 ring-emerald-200/80',
    draft: 'bg-gray-100 text-gray-700 ring-gray-200/80',
    closed: 'bg-amber-100 text-amber-900 ring-amber-200/80',
};

export function surveyStatusLabel(status) {
    return SURVEY_STATUS_LABELS[status] ?? status ?? '—';
}

export function surveyStatusBadgeClass(status) {
    return SURVEY_STATUS_BADGE_CLASSES[status] ?? 'bg-slate-100 text-slate-700 ring-slate-200/80';
}
