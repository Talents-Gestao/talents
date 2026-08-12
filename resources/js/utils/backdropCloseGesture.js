/**
 * Gesto de fecho do backdrop (FullScreenOverlay / Modal).
 * Fecha só se o pointer down e o click terminarem no backdrop —
 * evita fechar ao selecionar texto no formulário e soltar fora.
 */

/**
 * @param {boolean} startedOnBackdrop
 * @param {boolean} endedOnBackdrop
 */
export function shouldCloseOnBackdropClick(startedOnBackdrop, endedOnBackdrop) {
    return Boolean(startedOnBackdrop && endedOnBackdrop);
}

/**
 * @param {{ target: EventTarget | null, currentTarget: EventTarget | null }} event
 */
export function isPointerDownOnBackdrop(event) {
    return event.target === event.currentTarget;
}

/**
 * Tracker mínimo do gesto (útil em testes e para espelhar o estado dos componentes).
 */
export function createBackdropCloseTracker() {
    let startedOnBackdrop = false;

    return {
        /** @param {boolean} onBackdrop */
        pointerDown(onBackdrop) {
            startedOnBackdrop = Boolean(onBackdrop);
        },

        /** Marca início fora do backdrop (ex.: painel do modal). */
        pointerDownOnPanel() {
            startedOnBackdrop = false;
        },

        /**
         * @param {boolean} endedOnBackdrop
         * @returns {boolean} se deve fechar
         */
        click(endedOnBackdrop) {
            const shouldClose = shouldCloseOnBackdropClick(startedOnBackdrop, endedOnBackdrop);
            startedOnBackdrop = false;

            return shouldClose;
        },

        get startedOnBackdrop() {
            return startedOnBackdrop;
        },
    };
}
