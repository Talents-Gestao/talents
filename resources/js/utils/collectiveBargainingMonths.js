/** @type {{ value: number, label: string }[]} */
export const COLLECTIVE_BARGAINING_MONTHS = [
    { value: 1, label: 'Janeiro' },
    { value: 2, label: 'Fevereiro' },
    { value: 3, label: 'Março' },
    { value: 4, label: 'Abril' },
    { value: 5, label: 'Maio' },
    { value: 6, label: 'Junho' },
    { value: 7, label: 'Julho' },
    { value: 8, label: 'Agosto' },
    { value: 9, label: 'Setembro' },
    { value: 10, label: 'Outubro' },
    { value: 11, label: 'Novembro' },
    { value: 12, label: 'Dezembro' },
];

/**
 * @param {number | string | null | undefined} month
 * @returns {string | null}
 */
export function collectiveBargainingMonthLabel(month) {
    if (month === null || month === undefined || month === '') {
        return null;
    }

    const numeric = Number(month);

    return COLLECTIVE_BARGAINING_MONTHS.find((entry) => entry.value === numeric)?.label ?? null;
}
