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

export function isMultiDayRange(startIso, endIso) {
    return Boolean(endIso && startIso && endIso !== startIso);
}
