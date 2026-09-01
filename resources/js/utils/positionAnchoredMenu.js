/**
 * Posiciona um painel fixed junto a um âncora (ex.: botão de menu).
 * Prefere abrir abaixo; só sobe se não couber. Nunca usa altura estimada
 * maior que o espaço real — isso empurrava o menu para cima demais no Kanban.
 *
 * @param {{
 *   anchorRect: { top: number, bottom: number, right: number } | null | undefined,
 *   menuWidth: number,
 *   menuHeight: number,
 *   viewportWidth: number,
 *   viewportHeight: number,
 *   gap?: number,
 *   pad?: number,
 * }} opts
 * @returns {{ top: number, left: number } | null}
 */
export function positionAnchoredMenu({
    anchorRect,
    menuWidth,
    menuHeight,
    viewportWidth,
    viewportHeight,
    gap = 6,
    pad = 8,
}) {
    if (!anchorRect || menuWidth <= 0 || menuHeight <= 0) {
        return null;
    }

    const left = Math.max(
        pad,
        Math.min(anchorRect.right - menuWidth, viewportWidth - menuWidth - pad),
    );

    const below = anchorRect.bottom + gap;
    const spaceBelow = viewportHeight - pad - below;
    const spaceAbove = anchorRect.top - pad;

    if (spaceBelow >= menuHeight || spaceBelow >= spaceAbove) {
        let top = below;
        if (top + menuHeight > viewportHeight - pad) {
            top = Math.max(pad, viewportHeight - pad - menuHeight);
        }

        return { top, left };
    }

    return {
        top: Math.max(pad, anchorRect.top - menuHeight - gap),
        left,
    };
}
