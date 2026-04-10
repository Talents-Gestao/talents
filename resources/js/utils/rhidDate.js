/**
 * Conversao entre campos HTML5 (date / time) e strings da API RHID (YYYYMMDD, YYYYMMDDHHmm).
 */

/** @param {string} htmlDate YYYY-MM-DD */
export function toRhidYmd(htmlDate) {
    if (!htmlDate) {
        return '';
    }
    return String(htmlDate).replace(/-/g, '');
}

/** @param {string} ymd YYYYMMDD ou similar */
export function fromRhidYmd(ymd) {
    if (ymd == null || ymd === '') {
        return '';
    }
    const s = String(ymd).replace(/\D/g, '').slice(0, 8);
    if (s.length < 8) {
        return '';
    }
    return `${s.slice(0, 4)}-${s.slice(4, 6)}-${s.slice(6, 8)}`;
}

/**
 * @param {string} dateStr YYYY-MM-DD
 * @param {string} timeStr HH:mm ou vazio
 * @returns {string} YYYYMMDDHHmm
 */
export function toRhidYmdHm(dateStr, timeStr) {
    const ymd = toRhidYmd(dateStr);
    if (!ymd) {
        return '';
    }
    let hh = '00';
    let mm = '00';
    if (timeStr && String(timeStr).trim()) {
        const parts = String(timeStr).trim().split(':');
        const h = parseInt(parts[0], 10);
        const m = parseInt(parts[1] ?? '0', 10);
        if (!Number.isNaN(h)) {
            hh = String(Math.min(23, Math.max(0, h))).padStart(2, '0');
        }
        if (!Number.isNaN(m)) {
            mm = String(Math.min(59, Math.max(0, m))).padStart(2, '0');
        }
    }
    return `${ymd}${hh}${mm}`;
}

/** @returns {{ first: string, last: string }} datas HTML do mes corrente */
export function monthRangeHtmlDates() {
    const now = new Date();
    const y = now.getFullYear();
    const m = now.getMonth();
    const pad = (n) => String(n).padStart(2, '0');
    const first = `${y}-${pad(m + 1)}-01`;
    const lastDay = new Date(y, m + 1, 0).getDate();
    const last = `${y}-${pad(m + 1)}-${pad(lastDay)}`;
    return { first, last };
}

/** @returns {string} data HTML de hoje */
export function todayHtmlDate() {
    const d = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/**
 * Extrai lista de itens de respostas RHID paginadas ou array direto.
 * @param {unknown} payload
 * @returns {unknown[]}
 */
/**
 * Formata datas no formato Microsoft JSON: /Date(1647918000000-0300)/
 * @param {unknown} val
 * @returns {string} data local pt-BR ou string vazia se invalido
 */
export function formatRhidDotNetDate(val) {
    if (val == null || val === '') {
        return '';
    }
    const s = String(val);
    const m = s.match(/\/Date\((-?\d+)/);
    if (!m) {
        return '';
    }
    const d = new Date(parseInt(m[1], 10));
    if (Number.isNaN(d.getTime())) {
        return '';
    }
    return d.toLocaleDateString('pt-BR');
}

export function extractListItems(payload) {
    if (payload == null) {
        return [];
    }
    if (Array.isArray(payload)) {
        return payload;
    }
    if (typeof payload === 'object' && payload !== null && Array.isArray(payload.data)) {
        return payload.data;
    }
    return [];
}
