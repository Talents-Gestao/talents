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
