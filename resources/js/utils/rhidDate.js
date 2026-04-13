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

/**
 * Saldo de banco de horas: a API RHID envia minutos (inteiro, pode ser negativo).
 * @param {unknown} totalMinutes
 * @returns {string} ex.: "-9h 53min", "3h 54min", "0h 00min"
 */
export function formatRhidBankBalanceMinutes(totalMinutes) {
    if (totalMinutes == null || totalMinutes === '') {
        return '—';
    }
    const n = Number(totalMinutes);
    if (!Number.isFinite(n)) {
        return '—';
    }
    const sign = n < 0 ? '-' : '';
    const abs = Math.round(Math.abs(n));
    const h = Math.floor(abs / 60);
    const m = abs % 60;
    return `${sign}${h}h ${String(m).padStart(2, '0')}min`;
}

const BANK_NUMERIC_KEYS = [
    'saldoBancoHoras',
    'bancoHoras',
    'saldo',
    'minutesBank',
    'balance',
    'totalBancoHoras',
    'strBanco',
    'strSaldo',
];

/**
 * Minutos numéricos do saldo BH (mesma ordem de leitura que a UI de tabela).
 * @param {Record<string, unknown>|null|undefined} row
 * @returns {number|null}
 */
export function parseRhidBankBalanceMinutes(row) {
    if (row == null || typeof row !== 'object') {
        return null;
    }
    const strRaw = row.strSaldoBancoHoras;
    if (strRaw != null && String(strRaw).trim() !== '') {
        const raw = String(strRaw).trim();
        const neg = /^-/.test(raw);
        const s = raw.replace(/^-/, '');
        const hm = s.match(/^(\d{1,3}):(\d{2})$/);
        if (hm) {
            const h = parseInt(hm[1], 10);
            const m = parseInt(hm[2], 10);
            if (!Number.isNaN(h) && !Number.isNaN(m)) {
                const total = h * 60 + m;
                return neg ? -total : total;
            }
        }
        const hx = s.match(/(\d+)\s*h/i);
        const mx = s.match(/(\d+)\s*min/i);
        if (hx || mx) {
            const h = hx ? parseInt(hx[1], 10) : 0;
            const m = mx ? parseInt(mx[1], 10) : 0;
            if (!Number.isNaN(h) && !Number.isNaN(m)) {
                const total = h * 60 + m;
                return neg ? -total : total;
            }
        }
        const parsed = Number(raw.replace(',', '.'));
        if (Number.isFinite(parsed)) {
            return Math.round(parsed);
        }
        return null;
    }
    for (const k of BANK_NUMERIC_KEYS) {
        const v = row[k];
        if (v != null && v !== '') {
            const n = Number(v);
            if (Number.isFinite(n)) {
                return Math.round(n);
            }
            return null;
        }
    }
    return null;
}

/**
 * Nome amigavel para exibicao (cadastro / banco de horas).
 * Ordem alinhada ao uso tipico do RHID: strPersonName / personName primeiro;
 * objeto `person` aninhado tem precedencia sobre a raiz (mesmo criterio do backend).
 * @param {Record<string, unknown>|null|undefined} row
 * @returns {string}
 */
export function pickRhidPersonDisplayName(row) {
    if (row == null || typeof row !== 'object') {
        return '—';
    }
    const nest = row.person || row.Person;
    const trim = (v) => (v != null && String(v).trim() !== '' ? String(v).trim() : '');
    const keys = ['strPersonName', 'personName', 'name', 'nome', 'strNome', 'strName'];
    for (const k of keys) {
        const inner = nest && typeof nest === 'object' ? nest[k] : undefined;
        const tInner = trim(inner);
        if (tInner) {
            return tInner;
        }
        const tRoot = trim(row[k]);
        if (tRoot) {
            return tRoot;
        }
    }
    if (row.idPerson != null) {
        return `ID ${row.idPerson}`;
    }
    if (row.id != null) {
        return `ID ${row.id}`;
    }
    return '—';
}

/**
 * Texto de saldo BH para tabelas (mesma leitura que parseRhidBankBalanceMinutes).
 * @param {Record<string, unknown>|null|undefined} row
 * @returns {string}
 */
export function formatRhidBankBalanceDisplay(row) {
    const strRaw = row?.strSaldoBancoHoras;
    if (strRaw != null && String(strRaw).trim() !== '') {
        const s = String(strRaw).trim();
        if (/[hHmM]/.test(s) || /\d{1,3}:\d{2}/.test(s)) {
            return s;
        }
        const parsed = Number(s.replace(',', '.'));
        if (Number.isFinite(parsed)) {
            return formatRhidBankBalanceMinutes(parsed);
        }
        return s;
    }
    for (const k of BANK_NUMERIC_KEYS) {
        const v = row?.[k];
        if (v != null && v !== '') {
            const n = Number(v);
            if (Number.isFinite(n)) {
                return formatRhidBankBalanceMinutes(n);
            }
            return String(v);
        }
    }
    return '—';
}

/**
 * Extrai lista de itens de respostas RHID paginadas ou array direto.
 * @param {unknown} payload
 * @returns {unknown[]}
 */
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
