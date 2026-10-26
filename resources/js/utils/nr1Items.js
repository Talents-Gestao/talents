export const INITIAL_VISIBLE = 12;

export const SORT_OPTIONS = [
    { value: 'order', label: 'Ordem das perguntas' },
    { value: 'favorable', label: 'Mais favoráveis' },
    { value: 'attention', label: 'Mais pontos de atenção' },
];

/** Minúsculas e sem acento, para a busca ignorar "á" × "a". */
export const normalizeText = (text) =>
    String(text ?? '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .trim();

/**
 * Filtra os cartões por texto (enunciado ou dimensão) e por dimensão.
 * Trabalha só com os itens da campanha inteira: nenhum recorte por setor passa por aqui.
 */
export const filterItems = (items, { query = '', sectionId = null } = {}) => {
    const needle = normalizeText(query);

    return items.filter((item) => {
        if (sectionId !== null && sectionId !== '' && Number(item.section_id) !== Number(sectionId)) {
            return false;
        }
        if (needle === '') {
            return true;
        }

        return normalizeText(`${item.body} ${item.section_title}`).includes(needle);
    });
};

const byPosition = (a, b) => a.position - b.position;

/** Itens sem respostas (percentual nulo) ficam sempre no fim; empates seguem a ordem das perguntas. */
const byPercentDesc = (key) => (a, b) => {
    const left = a[key];
    const right = b[key];

    if (left === null && right === null) {
        return byPosition(a, b);
    }
    if (left === null) {
        return 1;
    }
    if (right === null) {
        return -1;
    }

    return right - left || byPosition(a, b);
};

export const sortItems = (items, sort = 'order') => {
    const copy = [...items];

    if (sort === 'favorable') {
        return copy.sort(byPercentDesc('favorable_pct'));
    }
    if (sort === 'attention') {
        return copy.sort(byPercentDesc('unfavorable_pct'));
    }

    return copy.sort(byPosition);
};

/**
 * Resultado pronto para a tela: lista ordenada e filtrada, o trecho visível (12 iniciais ou todas) e o total.
 */
export const prepareItems = (items, { query = '', sectionId = null, sort = 'order', showAll = false } = {}) => {
    const matching = sortItems(filterItems(items, { query, sectionId }), sort);
    const shown = showAll ? matching : matching.slice(0, INITIAL_VISIBLE);

    return {
        shown,
        total: matching.length,
        hiddenCount: matching.length - shown.length,
    };
};
