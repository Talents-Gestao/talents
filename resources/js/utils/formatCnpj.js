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

    return digits.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
}

export default formatCnpj;
