/**
 * Prévia de «Unificar sugestões duplicadas» (plano de ação NR-1): contagens e textos a partir dos grupos que o
 * servidor calcula (PsychosocialMeasureDeduplicator). Lógica pura, sem Vue.
 */

/** @param {Array<{removed: Array, manual: Array, merged: boolean}>} groups */
export function duplicatesSummary(groups) {
    const list = Array.isArray(groups) ? groups : [];
    const removed = list.reduce((total, group) => total + (group.removed?.length ?? 0), 0);
    const manual = list.reduce((total, group) => total + (group.manual?.length ?? 0), 0);

    return {
        dimensions: list.length,
        kept: list.length,
        merged: list.filter((group) => group.merged).length,
        removed,
        manual,
        canApply: removed > 0,
    };
}

const plural = (count, one, many) => `${count} ${count === 1 ? one : many}`;

/** Frase do cabeçalho do aviso. */
export function duplicatesHeadline(groups) {
    const summary = duplicatesSummary(groups);
    if (summary.dimensions === 0) return '';

    return `${plural(summary.dimensions, 'dimensão tem', 'dimensões têm')} mais de uma medida no plano.`;
}

/** Frase do resultado esperado da unificação, para a confirmação. */
export function duplicatesOutcome(groups) {
    const summary = duplicatesSummary(groups);
    const parts = [`${plural(summary.kept, 'medida mantida', 'medidas mantidas')}`];
    if (summary.merged > 0) parts.push(`${plural(summary.merged, 'com a ação juntada', 'com as ações juntadas')}`);
    parts.push(`${plural(summary.removed, 'sugestão removida', 'sugestões removidas')}`);
    if (summary.manual > 0) parts.push(`${plural(summary.manual, 'medida requer decisão manual', 'medidas requerem decisão manual')}`);

    return `${parts.join(' · ')}.`;
}
