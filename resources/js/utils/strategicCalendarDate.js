export function formatStrategicCalendarDateRange(startIso, endIso) {
    if (!startIso) return '—';

    const start = formatPtBr(startIso);
    if (!endIso || endIso === startIso) {
        return start;
    }

    return `${start} a ${formatPtBr(endIso)}`;
}

function formatPtBr(iso) {
    const date = new Date(`${String(iso).slice(0, 10)}T12:00:00`);
    if (Number.isNaN(date.getTime())) {
        return String(iso);
    }

    return date.toLocaleDateString('pt-BR');
}

/**
 * Intervalo inclusivo: 14/07 a 18/07 = 5 dias (14, 15, 16, 17 e 18).
 */
export function isMultiDayRange(startIso, endIso) {
    return Boolean(endIso && startIso && endIso !== startIso);
}

/**
 * Quantidade de dias inclusivos no intervalo (ou 1 se for dia único / inválido).
 */
export function inclusiveDayCount(startIso, endIso) {
    const start = sliceIso(startIso);
    const end = sliceIso(endIso) || start;
    if (!start) {
        return 0;
    }
    if (!end || end <= start) {
        return 1;
    }

    const startDate = new Date(`${start}T12:00:00`);
    const endDate = new Date(`${end}T12:00:00`);
    if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime())) {
        return 1;
    }

    const ms = endDate.getTime() - startDate.getTime();
    return Math.floor(ms / 86400000) + 1;
}

/**
 * Lista YYYY-MM-DD de cada dia do intervalo inclusivo.
 *
 * @returns {string[]}
 */
export function eachInclusiveIsoDay(startIso, endIso) {
    const start = sliceIso(startIso);
    const end = sliceIso(endIso) || start;
    if (!start) {
        return [];
    }

    const days = [];
    const cursor = new Date(`${start}T12:00:00`);
    const last = new Date(`${(end && end >= start ? end : start)}T12:00:00`);
    if (Number.isNaN(cursor.getTime()) || Number.isNaN(last.getTime())) {
        return start ? [start] : [];
    }

    while (cursor <= last) {
        const y = cursor.getFullYear();
        const m = String(cursor.getMonth() + 1).padStart(2, '0');
        const d = String(cursor.getDate()).padStart(2, '0');
        days.push(`${y}-${m}-${d}`);
        cursor.setDate(cursor.getDate() + 1);
    }

    return days;
}

function sliceIso(value) {
    if (value == null || value === '') {
        return null;
    }

    return String(value).slice(0, 10);
}
