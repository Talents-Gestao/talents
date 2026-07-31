/**
 * Máscara progressiva de CNPJ (00.000.000/0001-00) enquanto o usuário digita.
 *
 * @param {string|number|null|undefined} value
 * @returns {string}
 */
export function maskCnpj(value) {
    const digits = String(value ?? '').replace(/\D/g, '').slice(0, 14);
    if (digits.length <= 2) {
        return digits;
    }
    if (digits.length <= 5) {
        return `${digits.slice(0, 2)}.${digits.slice(2)}`;
    }
    if (digits.length <= 8) {
        return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5)}`;
    }
    if (digits.length <= 12) {
        return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8)}`;
    }

    return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8, 12)}-${digits.slice(12)}`;
}

/**
 * Formata um CNPJ para exibição (00.000.000/0001-00).
 * Mantém o valor original quando não houver 14 dígitos, para não mascarar
 * dados incompletos ou já formatados de outra forma.
 *
 * @param {string|number|null|undefined} value
 * @returns {string}
 */
export function formatCnpj(value) {
    const raw = String(value ?? '').trim();
    if (raw === '') {
        return '';
    }

    const digits = raw.replace(/\D/g, '');
    if (digits.length !== 14) {
        return raw;
    }

    return maskCnpj(digits);
}

export default formatCnpj;
