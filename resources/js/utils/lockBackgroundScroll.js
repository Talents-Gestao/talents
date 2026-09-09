let lockCount = 0;

/**
 * Trava o scroll da página (html/body) e dos contentores do shell/kanban
 * enquanto um modal está aberto. Usa contagem para nested modals.
 */
export function lockBackgroundScroll() {
    lockCount += 1;
    document.documentElement.classList.add('modal-open');
}

export function unlockBackgroundScroll() {
    lockCount = Math.max(0, lockCount - 1);
    if (lockCount === 0) {
        document.documentElement.classList.remove('modal-open');
    }
}
