/**
 * Helpers puros das listas financeiras (contas a receber / a pagar).
 *
 * Datas de pagamento são "só-dia" no calendário de America/Sao_Paulo, igual ao
 * backend (App\Support\Finance\FinancePaymentDate).
 */

const APP_TIMEZONE = 'America/Sao_Paulo';

export const GROUP_BY_SALE = 'sale';

const GROUP_PREFERENCE_KEY = 'talents.finance.receivables.groupBySale';

const isoDayFormatter = () =>
    new Intl.DateTimeFormat('en-CA', {
        timeZone: APP_TIMEZONE,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    });

/**
 * "Hoje" (YYYY-MM-DD) em São Paulo, independente do fuso do navegador.
 *
 * @param {Date} [now]
 * @returns {string}
 */
export function todayIsoInSaoPaulo(now = new Date()) {
    return isoDayFormatter().format(now);
}

/**
 * Converte o valor vindo do backend no dia (YYYY-MM-DD) de São Paulo.
 * Aceita "2026-08-12", "2026-08-12 00:00:00" (já local) ou ISO com fuso
 * ("2026-08-12T03:00:00.000000Z", serialização JSON do Eloquent).
 *
 * @param {string|null|undefined} value
 * @returns {string}
 */
export function saoPauloIsoDay(value) {
    if (!value) return '';
    const s = String(value).trim();
    if (/^\d{4}-\d{2}-\d{2}$/.test(s) || /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/.test(s)) {
        return s.slice(0, 10);
    }
    const d = new Date(s);
    if (Number.isNaN(d.getTime())) return '';
    return isoDayFormatter().format(d);
}

/**
 * Ordem padrão por aba: Recebidas/Pagas = data de pagamento; demais = vencimento.
 *
 * @param {string|null|undefined} status
 * @returns {'paid_at'|'due_date'}
 */
export function defaultSortForStatus(status) {
    return status === 'paid' ? 'paid_at' : 'due_date';
}

const groupKey = (item) =>
    item?.source === 'sale' && item.sale_id ? `sale-${item.sale_id}` : String(item?.id ?? '');

/**
 * Linhas da tabela de contas a receber. Na lista corrida cada linha é avulsa;
 * no modo agrupado, linhas consecutivas da mesma venda formam um bloco
 * (o backend já entrega as parcelas da venda juntas e na mesma página).
 *
 * @param {Array<object>} items
 * @param {boolean} grouped
 * @returns {Array<{ item: object, groupStart: boolean, groupSize: number }>}
 */
export function buildLedgerRows(items, grouped) {
    const data = Array.isArray(items) ? items : [];
    if (!grouped) {
        return data.map((item) => ({ item, groupStart: true, groupSize: 1 }));
    }

    const sizes = new Map();
    for (const item of data) {
        const key = groupKey(item);
        sizes.set(key, (sizes.get(key) ?? 0) + 1);
    }

    return data.map((item, index) => {
        const key = groupKey(item);
        const prevKey = index > 0 ? groupKey(data[index - 1]) : null;

        return {
            item,
            groupStart: key !== prevKey,
            groupSize: sizes.get(key) ?? 1,
        };
    });
}

/**
 * A visita do Inertia recarrega esta mesma lista (filtro, aba, ordenação, paginação, agrupar)?
 * Envios de formulário (POST/PUT/PATCH) e navegação para outra tela não contam:
 * os modais já têm o próprio «Salvando…».
 *
 * @param {{ method?: string, url?: URL|string }|null|undefined} visit
 * @param {string} currentHref
 * @returns {boolean}
 */
export function isListNavigation(visit, currentHref) {
    if (!visit || String(visit.method ?? 'get').toLowerCase() !== 'get') return false;
    try {
        const target = new URL(String(visit.url), currentHref);
        const current = new URL(currentHref);
        return target.pathname === current.pathname;
    } catch {
        return false;
    }
}

/**
 * Preferência "Agrupar por venda" deste navegador. Sem storage (modo privado,
 * bloqueio de site data), devolve null e a tela segue só com a query string.
 *
 * @param {Storage|undefined|null} [storage]
 * @returns {boolean|null}
 */
export function readGroupPreference(storage = globalThis.localStorage) {
    try {
        const value = storage?.getItem(GROUP_PREFERENCE_KEY);
        if (value === '1') return true;
        if (value === '0') return false;
        return null;
    } catch {
        return null;
    }
}

/**
 * @param {boolean} grouped
 * @param {Storage|undefined|null} [storage]
 */
export function writeGroupPreference(grouped, storage = globalThis.localStorage) {
    try {
        storage?.setItem(GROUP_PREFERENCE_KEY, grouped ? '1' : '0');
    } catch {
        // Storage indisponível: a escolha vale só para esta navegação (query string).
    }
}
