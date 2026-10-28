/**
 * Classes do botão do shell NR-1 (única fonte de estilo de botão do shell; CSS em nr1-shell-buttons.css).
 * Variantes: primary, secondary, inverse (claro sobre fundo escuro), ghost-on-dark, ghost-on-light, link, icon, chip.
 * Estados: repouso, hover, focus-visible, active, disabled e loading (todos na CSS, por variante).
 */
export const VARIANTS = ['primary', 'secondary', 'inverse', 'ghost-on-dark', 'ghost-on-light', 'link', 'icon', 'chip'];
export const SIZES = ['md', 'sm'];

export function buttonClasses({ variant = 'secondary', size = 'md', block = false, loading = false, selected = false } = {}) {
    const safeVariant = VARIANTS.includes(variant) ? variant : 'secondary';
    const safeSize = SIZES.includes(size) ? size : 'md';

    return [
        'n1b',
        `n1b--${safeVariant}`,
        `n1b--${safeSize}`,
        block ? 'n1b--block' : null,
        loading ? 'is-loading' : null,
        selected ? 'is-selected' : null,
    ].filter(Boolean);
}
