/**
 * Tela única de Tarefas: lógica pura do seletor de quadros e da descrição recolhível.
 */

/** Minúsculas e sem acentos, para a busca no seletor. */
export function normalizeSearch(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .trim();
}

/** Busca por nome do quadro, nome da empresa ou descrição (sem diferenciar maiúsculas nem acentos). */
export function filterBoards(boards, term) {
    const needle = normalizeSearch(term);
    const list = Array.isArray(boards) ? boards : [];
    if (!needle) {
        return list;
    }
    return list.filter((board) =>
        [board?.name, board?.company?.name, descriptionPlainText(board?.description)].some((field) =>
            normalizeSearch(field).includes(needle),
        ),
    );
}

/**
 * Favoritos primeiro; depois internos e por empresa, na ordem recebida do servidor.
 * Um quadro favorito aparece também no seu grupo (é lá que ele é reordenado).
 */
export function groupBoards(boards) {
    const list = Array.isArray(boards) ? boards : [];
    return {
        favorites: list.filter((board) => Boolean(board?.is_starred)),
        internal: list.filter((board) => Boolean(board?.is_internal)),
        company: list.filter((board) => !board?.is_internal),
    };
}

const ENTITIES = { '&nbsp;': ' ', '&amp;': '&', '&lt;': '<', '&gt;': '>', '&quot;': '"', '&#39;': "'", '&#039;': "'" };

/** Descrição (HTML do editor ou texto simples) como texto, para o «Ver descrição». */
export function descriptionPlainText(value) {
    if (typeof value !== 'string') {
        return '';
    }
    return value
        .replace(/<\s*br\s*\/?>/gi, '\n')
        .replace(/<\/\s*(p|div|li|h[1-6]|blockquote)\s*>/gi, '\n')
        .replace(/<[^>]*>/g, '')
        .replace(/&(nbsp|amp|lt|gt|quot|#0?39);/g, (entity) => ENTITIES[entity] ?? entity)
        .replace(/[ \t]+\n/g, '\n')
        .replace(/\n{3,}/g, '\n\n')
        .trim();
}

/**
 * Seções da tela de Tarefas (todos os quadros empilhados): favoritos primeiro; depois internos e por empresa,
 * sem repetir os favoritos (cada quadro aparece uma vez na tela).
 */
export function boardSections(boards, term = '') {
    const list = filterBoards(boards, term);
    return {
        favorites: list.filter((board) => Boolean(board?.is_starred)),
        internal: list.filter((board) => !board?.is_starred && Boolean(board?.is_internal)),
        company: list.filter((board) => !board?.is_starred && !board?.is_internal),
    };
}

/**
 * Quadro expandido? O que o usuário escolheu (guardado) vale; sem escolha, favoritos e o último usado abrem.
 * @param {Record<string, boolean>} stored
 */
export function isBoardExpanded(board, stored, focusBoardId) {
    const key = String(board?.id);
    if (stored && Object.prototype.hasOwnProperty.call(stored, key)) {
        return Boolean(stored[key]);
    }
    return Boolean(board?.is_starred) || (focusBoardId != null && Number(board?.id) === Number(focusBoardId));
}

/** Percentual de concluídos (0–100, inteiro) do resumo de um quadro. */
export function completionPercent(cardsCount, completedCount) {
    const total = Number(cardsCount) || 0;
    if (total <= 0) {
        return 0;
    }
    return Math.round((Math.min(Number(completedCount) || 0, total) / total) * 100);
}

/** Divide os ids em lotes (cada recarga parcial pede no máximo `size` quadros). */
export function chunkIds(ids, size = 6) {
    const list = [...new Set((Array.isArray(ids) ? ids : []).map(Number).filter(Number.isFinite))];
    const chunks = [];
    for (let i = 0; i < list.length; i += size) {
        chunks.push(list.slice(i, i + size));
    }
    return chunks;
}

/** Kanban com altura fixa (colunas rolam por dentro) só quando alguma lista passa de `threshold` cartões. */
export function kanbanNeedsFixedHeight(payload, threshold = 4) {
    return (payload?.lists || []).some((list) => (list?.cards?.length || 0) > threshold);
}
