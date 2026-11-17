/**
 * Textarea que cresce com o conteúdo (usado por Components/AutoGrowTextarea.vue).
 *
 * Regras:
 * - altura = altura do conteúdo, nunca abaixo do mínimo (minRows linhas) nem acima do máximo (maxVh da janela);
 * - acima do máximo aparece a rolagem interna; enquanto couber, sem barra;
 * - redimensionamento manual (alça vertical) vira o novo piso e pode passar do máximo;
 * - campo desabilitado (modelo travado, modo leitura) não tem máximo: o texto inteiro fica visível.
 */

/**
 * @param {{ contentHeight: number, minHeight: number, maxHeight?: number, userHeight?: number }} input
 * @returns {{ height: number, overflowY: 'hidden' | 'auto' }}
 */
export function computeAutoGrowHeight({ contentHeight, minHeight, maxHeight = Infinity, userHeight = 0 }) {
    const floor = Math.max(minHeight, userHeight || 0);
    const ceiling = Math.max(maxHeight, floor);
    const height = Math.min(Math.max(contentHeight, floor), ceiling);

    return { height, overflowY: contentHeight > height ? 'auto' : 'hidden' };
}

const px = (value) => Number.parseFloat(value) || 0;

/**
 * Mede o textarea e aplica altura e overflow. Devolve a altura aplicada (px).
 *
 * @param {HTMLTextAreaElement} el
 * @param {{ minRows?: number, maxVh?: number, userHeight?: number, win?: Window }} [options]
 */
export function applyAutoGrow(el, { minRows = 8, maxVh = 60, userHeight = 0, win = globalThis.window } = {}) {
    const style = win.getComputedStyle(el);
    const lineHeight = style.lineHeight === 'normal' ? px(style.fontSize) * 1.5 : px(style.lineHeight);
    const paddingY = px(style.paddingTop) + px(style.paddingBottom);
    const borderY = px(style.borderTopWidth) + px(style.borderBottomWidth);
    const borderBox = style.boxSizing === 'border-box';

    // Em border-box a altura do style inclui padding e borda; scrollHeight inclui padding, não a borda.
    const minHeight = minRows * lineHeight + (borderBox ? paddingY + borderY : 0);
    const maxHeight = el.matches(':disabled') ? Infinity : (win.innerHeight * maxVh) / 100;

    // Encolhe até o mínimo (não até 0) para medir: assim o texto que diminuiu também reduz a altura,
    // sem colapsar o campo e fazer a página pular.
    el.style.height = `${minHeight}px`;
    const contentHeight = el.scrollHeight + (borderBox ? borderY : -paddingY);

    const { height, overflowY } = computeAutoGrowHeight({ contentHeight, minHeight, maxHeight, userHeight });
    el.style.height = `${height}px`;
    el.style.overflowY = overflowY;

    return height;
}
