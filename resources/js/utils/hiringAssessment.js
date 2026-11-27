/**
 * Lógica pura da avaliação da candidatura (Contratação · Avaliação e dossiê).
 */

/** Critério novo: começa «Sem evidência» até alguém registrar a evidência. */
export function emptyCriterion() {
    return { criterio: '', evidencia: '', origem: '', avaliacao: 'sem_evidencia' };
}

/**
 * Classes do selo de avaliação do critério.
 *
 * @param {string} rating
 */
export function ratingClass(rating) {
    return (
        {
            atende: 'bg-emerald-50 text-emerald-800',
            parcial: 'bg-amber-50 text-amber-800',
            nao_atende: 'bg-red-50 text-red-700',
            sem_evidencia: 'bg-slate-100 text-slate-600',
        }[rating] ?? 'bg-slate-100 text-slate-600'
    );
}

/**
 * Critérios avaliados sem evidência (o servidor recusa; a tela avisa antes).
 *
 * @param {Array<{ criterio?: string, evidencia?: string, avaliacao?: string }>} criteria
 * @returns {string[]} nomes dos critérios com problema
 */
export function criteriaMissingEvidence(criteria) {
    return (criteria ?? [])
        .filter((c) => (c.criterio ?? '').trim() !== '')
        .filter((c) => (c.avaliacao ?? 'sem_evidencia') !== 'sem_evidencia' && (c.evidencia ?? '').trim() === '')
        .map((c) => c.criterio.trim());
}
