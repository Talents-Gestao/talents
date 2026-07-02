/**
 * Utilitários para datas "só-dia" (sem hora, ex.: cast `date` no Laravel).
 *
 * Talents opera com horário oficial **America/Sao_Paulo (UTC-3)**:
 *   - O backend Laravel está em `America/Sao_Paulo` (config/app.php).
 *   - Aqui no front, "hoje" / "amanhã" / "ontem" são calculados na timezone
 *     de São Paulo, independente da timezone do navegador. Isto evita que
 *     um utilizador que viaje ou tenha o sistema em outro fuso veja uma data
 *     diferente da que a equipa cadastrou no calendário estratégico.
 *
 * Implementação:
 *   - Para datas só-dia (`YYYY-MM-DD`), extraímos os componentes e construímos
 *     um Date "âncora" ao meio-dia local — assim a formatação por extenso fica
 *     no dia certo, sem deslocamento por DST.
 *   - Para "hoje", usamos Intl.DateTimeFormat com timezone fixo de São Paulo.
 */

const APP_TIMEZONE = 'America/Sao_Paulo';

/**
 * Retorna o ano/mês/dia "hoje" em São Paulo, sem depender do fuso do navegador.
 *
 * @returns {{ y: number, m: number, d: number }}
 */
function todayInSaoPauloParts() {
    const fmt = new Intl.DateTimeFormat('en-CA', {
        timeZone: APP_TIMEZONE,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    });
    const parts = fmt.formatToParts(new Date());
    const get = (type) => Number(parts.find((p) => p.type === type)?.value);
    return { y: get('year'), m: get('month'), d: get('day') };
}

/**
 * Devolve um `Date` local ao meio-dia para a parte `YYYY-MM-DD` de uma string.
 *
 * @param {string|null|undefined} iso
 * @returns {Date|null}
 */
export function parseDateOnly(iso) {
    if (!iso) return null;
    const s = String(iso).trim();
    if (!s) return null;
    const m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (m) {
        const [, y, mo, dd] = m;
        return new Date(Number(y), Number(mo) - 1, Number(dd), 12, 0, 0);
    }
    const d = new Date(s);
    return Number.isNaN(d.getTime()) ? null : d;
}

function capitalizeFirst(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

/**
 * Ex.: "Terça-feira, 13 de maio de 2026"
 */
export function formatDateLong(iso) {
    const d = parseDateOnly(iso);
    if (!d) return '';
    return capitalizeFirst(
        d.toLocaleDateString('pt-BR', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        }),
    );
}

/**
 * Ex.: "13 mai" (sem ano)
 */
export function formatDateShort(iso) {
    const d = parseDateOnly(iso);
    if (!d) return '—';
    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' });
}

/**
 * Início do dia local (00:00) para comparar com outras datas locais.
 *
 * @param {Date} d
 */
export function startOfLocalDay(d) {
    return new Date(d.getFullYear(), d.getMonth(), d.getDate());
}

/**
 * Date "âncora" do dia de hoje em São Paulo (12:00 local do navegador,
 * mas com ano/mês/dia da timezone de SP — assim comparações de dia ficam
 * corretas mesmo se o usuário estiver em outro fuso).
 *
 * @returns {Date}
 */
export function todayInSaoPaulo() {
    const { y, m, d } = todayInSaoPauloParts();
    return new Date(y, m - 1, d, 12, 0, 0);
}

/**
 * Diferença em dias entre a data informada e "hoje em São Paulo".
 * 0 = hoje, 1 = amanhã, -1 = ontem.
 *
 * @param {string|null|undefined} iso
 * @returns {number|null}
 */
export function daysFromToday(iso) {
    const d = parseDateOnly(iso);
    if (!d) return null;
    const e0 = startOfLocalDay(d).getTime();
    const t0 = startOfLocalDay(todayInSaoPaulo()).getTime();
    return Math.round((e0 - t0) / 86400000);
}

/**
 * Ex.: "13/06/2026" — data absoluta para tooltips e contexto administrativo.
 */
export function formatDateNumeric(iso) {
    const d = parseDateOnly(iso);
    if (!d) return '';
    return d.toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

/**
 * Nome relativo ao "hoje" em São Paulo (estilo Google Calendar/Tasks).
 *
 * @param {string|null|undefined} iso
 * @param {{ weekDayWindow?: number, pastWeekDayWindow?: number }} [options]
 * @returns {string}
 */
export function formatRelativeDate(iso, options = {}) {
    const weekDayWindow = options.weekDayWindow ?? 6;
    const pastWeekDayWindow = options.pastWeekDayWindow ?? 6;

    const diff = daysFromToday(iso);
    if (diff === null) return '—';

    if (diff === 0) return 'Hoje';
    if (diff === 1) return 'Amanhã';
    if (diff === -1) return 'Ontem';

    const d = parseDateOnly(iso);
    if (!d) return '—';

    if (diff > 1 && diff <= weekDayWindow) {
        return capitalizeFirst(d.toLocaleDateString('pt-BR', { weekday: 'long' }));
    }

    if (diff < -1 && diff >= -pastWeekDayWindow) {
        return capitalizeFirst(d.toLocaleDateString('pt-BR', { weekday: 'long' }));
    }

    const today = todayInSaoPaulo();
    if (d.getFullYear() === today.getFullYear()) {
        return formatDateShort(iso);
    }

    return formatDateNumeric(iso);
}

/**
 * Cabeçalho de grupo por dia (lista compacta): "Hoje", "Amanhã" ou "seg., 13 de jun.".
 */
export function formatRelativeDayHeader(iso) {
    const diff = daysFromToday(iso);
    if (diff === 0) return 'Hoje';
    if (diff === 1) return 'Amanhã';
    if (diff === -1) return 'Ontem';

    const d = parseDateOnly(iso);
    if (!d) return '';

    return capitalizeFirst(
        d.toLocaleDateString('pt-BR', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
        }),
    );
}

/**
 * Cabeçalho de agenda: relativo próximo ou data por extenso.
 */
export function formatRelativeAgendaHeader(iso) {
    const diff = daysFromToday(iso);
    if (diff === 0) return 'Hoje';
    if (diff === 1) return 'Amanhã';
    if (diff === -1) return 'Ontem';

    return formatDateLong(iso);
}

/**
 * Chip com tom visual para dashboards (próximo evento, etc.).
 *
 * @returns {{ label: string, tone: 'today'|'soon'|'past'|'far' }|null}
 */
export function formatRelativeDateChip(iso) {
    const diff = daysFromToday(iso);
    if (diff === null) return null;

    const label = formatRelativeDate(iso);
    let tone = 'far';

    if (diff === 0) {
        tone = 'today';
    } else if (diff === 1 || (diff > 1 && diff <= 7)) {
        tone = 'soon';
    } else if (diff < 0) {
        tone = 'past';
    }

    return { label, tone };
}
