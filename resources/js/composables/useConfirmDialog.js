import { reactive } from 'vue';

/**
 * Estado singleton do diálogo de confirmação (substitui window.confirm).
 * @typedef {{ title: string, message: string, confirmLabel: string, cancelLabel: string, variant: 'danger' | 'primary' }} ConfirmDialogOptions
 */

/** @type {{ show: boolean } & ConfirmDialogOptions} */
const state = reactive({
    show: false,
    title: '',
    message: '',
    confirmLabel: 'Confirmar',
    cancelLabel: 'Cancelar',
    variant: /** @type {'danger' | 'primary'} */ ('danger'),
});

/** @type {((value: boolean) => void) | null} */
let resolvePromise = null;

/**
 * @param {string} text
 * @returns {'danger' | 'primary'}
 */
function inferVariant(text) {
    const lower = text.toLowerCase();
    if (
        /excluir|remover|apagar|desativar|permanentemente|não pode ser desfeita|nao pode ser desfeita/.test(
            lower,
        )
    ) {
        return 'danger';
    }
    return 'primary';
}

/**
 * @param {string} text
 * @returns {string}
 */
function inferConfirmLabel(text) {
    const lower = text.toLowerCase().trim();
    if (lower.startsWith('excluir')) return 'Excluir';
    if (lower.startsWith('remover')) return 'Remover';
    if (lower.startsWith('arquivar')) return 'Arquivar';
    if (lower.startsWith('reenviar') || lower.startsWith('enviar')) return 'Enviar';
    if (lower.startsWith('reprocessar')) return 'Reprocessar';
    if (lower.startsWith('desativar')) return 'Desativar';
    return 'Confirmar';
}

/**
 * @param {string} raw
 * @returns {{ title: string, message: string }}
 */
function splitMessage(raw) {
    const normalized = String(raw ?? '').replace(/\r\n/g, '\n').trim();
    const parts = normalized.split(/\n\n+/);
    if (parts.length >= 2) {
        return {
            title: parts[0].trim(),
            message: parts.slice(1).join('\n\n').trim(),
        };
    }
    return { title: normalized, message: '' };
}

/**
 * Abre o modal de confirmação. API compatível com confirm(): aceita string ou opções.
 *
 * @param {string | Partial<ConfirmDialogOptions> & { message?: string }} messageOrOptions
 * @returns {Promise<boolean>}
 */
export function confirmDialog(messageOrOptions) {
    if (resolvePromise) {
        resolvePromise(false);
        resolvePromise = null;
    }

    /** @type {Partial<ConfirmDialogOptions> & { message?: string }} */
    let options;
    if (typeof messageOrOptions === 'string') {
        const split = splitMessage(messageOrOptions);
        options = {
            title: split.title,
            message: split.message,
            confirmLabel: inferConfirmLabel(split.title),
            variant: inferVariant(messageOrOptions),
        };
    } else {
        const rawMessage = messageOrOptions.message ?? messageOrOptions.title ?? '';
        const split =
            messageOrOptions.title != null
                ? { title: messageOrOptions.title, message: messageOrOptions.message ?? '' }
                : splitMessage(rawMessage);
        options = {
            title: split.title,
            message: split.message,
            confirmLabel:
                messageOrOptions.confirmLabel ?? inferConfirmLabel(split.title || rawMessage),
            cancelLabel: messageOrOptions.cancelLabel,
            variant: messageOrOptions.variant ?? inferVariant(`${split.title} ${split.message}`),
        };
    }

    state.title = options.title ?? '';
    state.message = options.message ?? '';
    state.confirmLabel = options.confirmLabel ?? 'Confirmar';
    state.cancelLabel = options.cancelLabel ?? 'Cancelar';
    state.variant = options.variant ?? 'danger';
    state.show = true;

    return new Promise((resolve) => {
        resolvePromise = resolve;
    });
}

function finish(result) {
    state.show = false;
    const resolve = resolvePromise;
    resolvePromise = null;
    resolve?.(result);
}

export function acceptConfirmDialog() {
    finish(true);
}

export function cancelConfirmDialog() {
    finish(false);
}

export function useConfirmDialogState() {
    return state;
}
