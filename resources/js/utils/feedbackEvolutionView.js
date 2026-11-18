/**
 * Apresentação da Evolução do colaborador (só leitura do que o servidor manda): rótulo e estilo do tom de cada
 * período (sempre com texto, não só cor), texto do estado vazio e lista de referências («S1 · mar/2026»).
 */
export const TONE_STYLES = {
    positivo: { label: 'Positivo', classes: 'bg-emerald-50 text-emerald-900 ring-emerald-300', icon: 'up' },
    misto: { label: 'Misto', classes: 'bg-sky-50 text-sky-900 ring-sky-300', icon: 'mixed' },
    atencao: { label: 'Atenção', classes: 'bg-amber-50 text-amber-950 ring-amber-300', icon: 'attention' },
    neutro: { label: 'Neutro', classes: 'bg-slate-50 text-slate-800 ring-slate-300', icon: 'neutral' },
};

export function toneStyle(tone) {
    return TONE_STYLES[tone] ?? TONE_STYLES.neutro;
}

/** Texto do estado vazio: quantos feedbacks com relatório existem e quantos faltam. */
export function emptyStateText(evolution) {
    const min = evolution?.min_sessions ?? 2;
    const ready = evolution?.eligible_count ?? 0;
    const total = evolution?.sessions_count ?? 0;
    const missing = Math.max(0, min - ready);
    const base = `A evolução aparece quando houver pelo menos ${min} feedbacks com relatório pronto.`;
    if (total === 0) return `${base} Esta pessoa ainda não tem feedbacks visíveis para você.`;

    return `${base} Hoje há ${ready} de ${total} ${total === 1 ? 'feedback' : 'feedbacks'} com relatório; ${missing === 1 ? 'falta 1' : `faltam ${missing}`}.`;
}

/** «S1 · mar/2026, S3 · nov/2026» a partir das fontes apresentadas pelo servidor. */
export function sourcesText(fontes = []) {
    return fontes.map((f) => f.label).join(', ');
}

/** Atualizar a página enquanto a narrativa estiver sendo gerada. */
export function shouldPoll(narrative) {
    return narrative?.status === 'processing';
}
