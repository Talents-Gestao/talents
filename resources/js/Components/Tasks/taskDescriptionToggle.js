/**
 * Descrição recolhível das etapas do checklist (lógica pura, testada em tests/JavaScript/taskDescriptionToggle.test.mjs).
 * O botão é só o ícone de seta: o texto vai em aria-label e title.
 */

/** Rótulo acessível do botão: «Ocultar descrição» aberto; recolhido, «Ver descrição» com texto, senão «Adicionar descrição». */
export function descriptionToggleLabel({ open = false, filled = false } = {}) {
    if (open) return 'Ocultar descrição';
    return filled ? 'Ver descrição' : 'Adicionar descrição';
}

/** Indicador discreto (ponto) de que há descrição, para dar a pista sem abrir. */
export function showsFilledIndicator({ open = false, filled = false } = {}) {
    return Boolean(filled) && !open;
}

export const hasText = (value) => Boolean(String(value ?? '').trim());

/**
 * Estado inicial das etapas: começa aberta só quando já tem descrição. Ao reabrir o cartão, recomeça; numa recarga do
 * mesmo cartão, mantém o que o usuário abriu ou fechou e só inicia as etapas novas.
 *
 * @param {Record<string, boolean>} current
 * @param {Array<{id: number|string, description?: string|null}>} items
 */
export function initialDescriptionState(current, items, { reopened = true } = {}) {
    const next = reopened ? {} : { ...current };
    for (const item of items || []) {
        const key = `item-${item.id}`;
        if (next[key] === undefined) {
            next[key] = hasText(item.description);
        }
    }
    return next;
}

/** Abre ou fecha uma descrição (sem tocar no texto, que fica só oculto). */
export function toggleDescription(state, key) {
    return { ...state, [key]: !state?.[key] };
}
