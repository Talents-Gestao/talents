const STORAGE_KEY = 'talents.inertiaNavStack';
const MAX_ENTRIES = 40;

/**
 * @param {string} url
 * @returns {string}
 */
export function normalizeAppUrl(url) {
    try {
        const parsed = new URL(url, window.location.origin);
        return `${parsed.pathname}${parsed.search}`;
    } catch {
        return String(url ?? '');
    }
}

/**
 * @returns {string[]}
 */
function readStack() {
    try {
        const raw = sessionStorage.getItem(STORAGE_KEY);
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed.map(String) : [];
    } catch {
        return [];
    }
}

/**
 * @param {string[]} stack
 */
function writeStack(stack) {
    try {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(stack.slice(-MAX_ENTRIES)));
    } catch {
        // sessionStorage indisponível — ignora
    }
}

/**
 * Remove URLs consecutivas duplicadas no topo do stack.
 *
 * @param {string[]} stack
 */
function dedupeTrailing(stack) {
    while (stack.length > 1 && stack[stack.length - 1] === stack[stack.length - 2]) {
        stack.pop();
    }
}

/**
 * Regista uma visita Inertia no histórico da aplicação.
 *
 * @param {string} url
 * @param {{ replace?: boolean }} [options]
 */
export function recordAppNavigation(url, options = {}) {
    const path = normalizeAppUrl(url);
    if (!path || path.startsWith('/login') || path.startsWith('/register')) {
        return;
    }

    const stack = readStack();
    const replace = Boolean(options.replace);

    if (replace) {
        if (stack.length === 0) {
            stack.push(path);
        } else {
            stack[stack.length - 1] = path;
        }
        dedupeTrailing(stack);
    } else if (stack[stack.length - 1] !== path) {
        stack.push(path);
    }

    writeStack(stack);
}

/**
 * URL anterior distinta da atual (ou null).
 *
 * @param {string} [currentUrl]
 * @returns {string|null}
 */
export function getPreviousAppUrl(currentUrl = window.location.href) {
    const current = normalizeAppUrl(currentUrl);
    const stack = readStack();

    for (let i = stack.length - 1; i >= 0; i -= 1) {
        if (stack[i] && stack[i] !== current) {
            return stack[i];
        }
    }

    return null;
}

/**
 * Consome a entrada atual do stack ao voltar (evita loops).
 *
 * @param {string} [currentUrl]
 * @returns {string|null} URL de destino
 */
export function consumeBackTarget(currentUrl = window.location.href) {
    const current = normalizeAppUrl(currentUrl);
    const stack = readStack();

    while (stack.length > 0 && stack[stack.length - 1] === current) {
        stack.pop();
    }

    const target = stack.length > 0 ? stack[stack.length - 1] : null;
    writeStack(stack);

    return target;
}
