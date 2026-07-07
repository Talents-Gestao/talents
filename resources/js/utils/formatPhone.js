/**
 * Mantém só dígitos (máx. 11 — celular BR com DDD).
 *
 * @param {string|number|null|undefined} value
 * @returns {string}
 */
export function phoneDigits(value) {
    return String(value ?? '').replace(/\D/g, '').slice(0, 11);
}

/**
 * Aplica máscara brasileira conforme o usuário digita.
 * Fixo (10): (11) 3456-7890 · Celular (11): (11) 98765-4321
 *
 * @param {string|number|null|undefined} value
 * @returns {string}
 */
export function maskPhoneBr(value) {
    const digits = phoneDigits(value);
    if (digits.length === 0) {
        return '';
    }
    if (digits.length <= 2) {
        return `(${digits}`;
    }
    if (digits.length <= 6) {
        return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
    }
    if (digits.length <= 10) {
        return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;
    }

    return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7, 11)}`;
}

/**
 * Formata para exibição (alias de {@link maskPhoneBr}).
 *
 * @param {string|number|null|undefined} value
 * @returns {string}
 */
export function formatPhoneBr(value) {
    const digits = phoneDigits(value);
    if (digits.length === 0) {
        return '';
    }

    return maskPhoneBr(digits);
}

export default maskPhoneBr;
