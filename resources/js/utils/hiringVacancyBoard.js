/**
 * Lógica pura do quadro de vagas (Contratação · Vagas).
 */

/**
 * Agrupa candidaturas por etapa, na ordem de sort_order.
 *
 * @param {Array<{ id: number }>} stages
 * @param {Array<{ id: number, stage_id: number, sort_order?: number }>} applications
 * @returns {Record<number, Array<object>>}
 */
export function groupApplicationsByStage(stages, applications) {
    /** @type {Record<number, Array<object>>} */
    const columns = {};
    for (const stage of stages ?? []) {
        columns[stage.id] = [];
    }
    for (const application of applications ?? []) {
        if (!columns[application.stage_id]) {
            continue;
        }
        columns[application.stage_id].push({ ...application });
    }
    for (const id of Object.keys(columns)) {
        columns[id].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0) || a.id - b.id);
    }
    return columns;
}

/**
 * Devolve uma cópia da lista com o item da posição `index` deslocado em `delta` (±1).
 * Fora dos limites, devolve a cópia sem mudança.
 *
 * @template T
 * @param {T[]} list
 * @param {number} index
 * @param {number} delta
 * @returns {T[]}
 */
export function shiftItem(list, index, delta) {
    const next = [...(list ?? [])];
    const target = index + delta;
    if (index < 0 || index >= next.length || target < 0 || target >= next.length) {
        return next;
    }
    const [item] = next.splice(index, 1);
    next.splice(target, 0, item);
    return next;
}

/**
 * Regras de exclusão de etapa (espelho das regras do servidor, só para a interface).
 *
 * @param {{ is_hired: boolean, applications_count: number }} stage
 * @param {number} totalStages
 * @returns {string|null} motivo do bloqueio, ou null se pode excluir
 */
export function stageDeleteBlockReason(stage, totalStages) {
    if (totalStages <= 1) {
        return 'A vaga precisa ter pelo menos uma etapa.';
    }
    if (stage.is_hired) {
        return 'A etapa de contratado não pode ser excluída.';
    }
    if ((stage.applications_count ?? 0) > 0) {
        return 'Mova os candidatos desta etapa antes de excluí-la.';
    }
    return null;
}

/**
 * Texto de um evento do histórico da candidatura.
 *
 * @param {{ type: string, from_stage_name?: string|null, to_stage_name?: string|null, note?: string|null }} event
 * @returns {string}
 */
export function describeApplicationEvent(event) {
    const removed = 'etapa excluída';
    if (event.type === 'added') {
        return `Incluído na etapa ${event.to_stage_name ?? removed}`;
    }
    if (event.type === 'moved') {
        return `De ${event.from_stage_name ?? removed} para ${event.to_stage_name ?? removed}`;
    }
    return event.note ?? '';
}
