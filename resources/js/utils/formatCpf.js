/**
 * Máscara progressiva de CPF (000.000.000-00) enquanto o usuário digita.
 *
 * @param {string|number|null|undefined} value
 * @returns {string}
 */
export function maskCpf(value) {
    const digits = String(value ?? '').replace(/\D/g, '').slice(0, 11);
    if (digits.length <= 3) {
        return digits;
    }
    if (digits.length <= 6) {
        return `${digits.slice(0, 3)}.${digits.slice(3)}`;
    }
    if (digits.length <= 9) {
        return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6)}`;
    }

    return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9)}`;
}

/**
 * Formata um CPF para exibição (000.000.000-00).
 * Mantém o valor original quando não houver 11 dígitos.
 *
 * @param {string|number|null|undefined} value
 * @returns {string}
 */
export function formatCpf(value) {
    const raw = String(value ?? '').trim();
    if (raw === '') {
        return '';
    }

    const digits = raw.replace(/\D/g, '');
    if (digits.length !== 11) {
        return raw;
    }

    return maskCpf(digits);
}

export default formatCpf;
