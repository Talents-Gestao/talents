/**
 * Máscara monetária pt-BR para inputs (exibição) e parse para número com ponto (API).
 * Não altera formatBRL (somente leitura com símbolo R$).
 */

/**
 * Converte string/número (pt-BR ou en) em número finito, ou null se vazio/inválido.
 * @param {unknown} value
 * @returns {number|null}
 */
export function parseMoneyToNumber(value) {
    if (value === null || value === undefined) {
        return null;
    }

    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : null;
    }

    const raw = String(value).trim();
    if (raw === '') {
        return null;
    }

    let normalized = raw.replace(/[^\d,.-]/g, '');
    if (normalized === '' || normalized === '-' || normalized === ',' || normalized === '.') {
        return null;
    }

    if (normalized.includes(',')) {
        normalized = normalized.replace(/\./g, '').replace(',', '.');
    }

    const n = Number(normalized);
    return Number.isFinite(n) ? n : null;
}

/**
 * Exibição pt-BR sem símbolo: 1234.5 → "1.234,50". Vazio → "".
 * @param {unknown} value
 * @returns {string}
 */
export function formatMoneyDisplay(value) {
    const n = parseMoneyToNumber(value);
    if (n === null) {
        return '';
    }

    return n.toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

/**
 * Valor canónico para formulário/API (ponto decimal): 1234.5 → "1234.50".
 * @param {unknown} value
 * @returns {string}
 */
export function formatMoneyModel(value) {
    const n = parseMoneyToNumber(value);
    if (n === null) {
        return '';
    }

    return n.toFixed(2);
}

/**
 * Converte valor monetário em centavos inteiros (≥ 0).
 * @param {unknown} value
 * @returns {number}
 */
export function moneyToCents(value) {
    const n = parseMoneyToNumber(value);
    if (n === null) {
        return 0;
    }

    return Math.max(0, Math.round(n * 100));
}

/**
 * Centavos → modelo de formulário com ponto.
 * @param {unknown} cents
 * @returns {string}
 */
export function centsToMoneyModel(cents) {
    return formatMoneyModel((Number(cents) || 0) / 100);
}
