import { eachInclusiveIsoDay, isMultiDayRange } from './strategicCalendarDate.js';

/** Espaço do número do dia + altura de cada lane (alinhado ao CSS do mês). */
export const MONTH_DAY_NUMBER_OFFSET_REM = 1.55;
export const MONTH_LANE_HEIGHT_REM = 1.2;

/** Mesma ordem dos chips / painel do dia — férias não têm prioridade extra. */
export const CALENDAR_KIND_ORDER = ['birthday', 'ritual', 'event', 'task', 'leave'];

/**
 * Só há tirinha multi-dia quando existe `ends_on` (intervalo explícito).
 * Sem término: evento de um dia — inclusive ocorrências de recorrência
 * (nelas `range_starts_on` é a âncora da série, não o início de um span).
 */
export function isSpanningCalendarEvent(item) {
    const end = sliceIso(item?.ends_on);
    if (!end) {
        return false;
    }

    const start = sliceIso(item?.range_starts_on || item?.occurs_on);

    return isMultiDayRange(start, end);
}

/**
 * @param {Record<string, unknown>} item
 * @returns {string}
 */
export function spanningEventKey(item) {
    const start = sliceIso(item?.range_starts_on || item?.occurs_on);
    const end = sliceIso(item?.ends_on);
    const source = item?.source_id ?? item?.id;

    return `${source}|${start}|${end}`;
}

/**
 * @param {string|null|undefined} kind
 * @returns {number}
 */
export function calendarKindRank(kind) {
    const index = CALENDAR_KIND_ORDER.indexOf(String(kind ?? ''));

    return index === -1 ? CALENDAR_KIND_ORDER.length : index;
}

/**
 * Altura da faixa reservada às tirinhas (não pode cobrir chips / “+N mais”).
 *
 * @param {number} laneCount
 * @returns {string}
 */
export function spanningLanesOverlayHeight(laneCount) {
    const lanes = Math.max(0, Number(laneCount) || 0);
    if (!lanes) {
        return '0';
    }

    return `${MONTH_DAY_NUMBER_OFFSET_REM + lanes * MONTH_LANE_HEIGHT_REM}rem`;
}

/**
 * padding-top da lista de chips do dia, alinhado às lanes.
 *
 * @param {number} laneCount
 * @returns {string}
 */
export function dayCellChipsPaddingTop(laneCount) {
    const lanes = Math.max(0, Number(laneCount) || 0);
    if (!lanes) {
        return '0.125rem';
    }

    return `${lanes * MONTH_LANE_HEIGHT_REM + 0.2}rem`;
}

/**
 * Empacota eventos multi-dia de uma semana (7 células) em lanes horizontais estilo Google Agenda.
 * O span é sempre inclusivo (ex.: 14 a 18 = todos os dias de 14 até 18 nessa semana).
 * A ordem prioriza continuações da semana anterior (sem buracos no intervalo),
 * depois início, duração e o mesmo rank de kind dos chips do dia.
 *
 * @param {Array<{ iso: string|null, items?: Array<Record<string, unknown>> }>} weekCells
 * @param {{ maxLanes?: number, maxSinglePerCell?: number }} [options]
 * @returns {{
 *   segments: Array<{
 *     key: string,
 *     item: Record<string, unknown>,
 *     startCol: number,
 *     endCol: number,
 *     span: number,
 *     lane: number,
 *     continuesBefore: boolean,
 *     continuesAfter: boolean,
 *   }>,
 *   laneCount: number,
 *   singleDayByCol: Array<Array<Record<string, unknown>>>,
 *   moreByCol: number[],
 *   spanningCols: boolean[],
 * }}
 */
export function packWeekSpanningSegments(weekCells, options = {}) {
    const maxLanes = options.maxLanes ?? 3;
    const maxSinglePerCell = options.maxSinglePerCell ?? 2;
    const cells = Array.isArray(weekCells) ? weekCells : [];

    const firstIso = sliceIso(cells[0]?.iso);
    const lastIso = sliceIso(cells[6]?.iso);

    /** @type {Map<string, number>} */
    const colByIso = new Map();
    for (let col = 0; col < 7; col += 1) {
        const iso = sliceIso(cells[col]?.iso);
        if (iso) {
            colByIso.set(iso, col);
        }
    }

    /** @type {Array<{ key: string, item: Record<string, unknown>, startCol: number, endCol: number, span: number, continuesBefore: boolean, continuesAfter: boolean }>} */
    const candidates = [];
    const seen = new Set();

    for (const cell of cells) {
        for (const item of cell?.items ?? []) {
            if (!isSpanningCalendarEvent(item)) {
                continue;
            }

            const key = spanningEventKey(item);
            if (seen.has(key)) {
                continue;
            }
            seen.add(key);

            const rangeStart = sliceIso(item.range_starts_on || item.occurs_on);
            const rangeEnd = sliceIso(item.ends_on);
            if (!rangeStart || !rangeEnd) {
                continue;
            }

            let startCol = -1;
            let endCol = -1;
            for (const iso of eachInclusiveIsoDay(rangeStart, rangeEnd)) {
                if (!colByIso.has(iso)) {
                    continue;
                }
                const col = colByIso.get(iso);
                if (startCol === -1) {
                    startCol = col;
                }
                endCol = col;
            }

            if (startCol === -1 || endCol === -1) {
                continue;
            }

            candidates.push({
                key,
                item,
                startCol,
                endCol,
                span: endCol - startCol + 1,
                continuesBefore: Boolean(firstIso && rangeStart < firstIso),
                continuesAfter: Boolean(lastIso && rangeEnd > lastIso),
            });
        }
    }

    // Continuações da semana anterior primeiro: evita “buraco” no meio de um intervalo multi-semana.
    candidates.sort((a, b) => {
        const contDiff = Number(b.continuesBefore) - Number(a.continuesBefore);
        if (contDiff !== 0) {
            return contDiff;
        }

        const startDiff = a.startCol - b.startCol;
        if (startDiff !== 0) {
            return startDiff;
        }

        const spanDiff = b.span - a.span;
        if (spanDiff !== 0) {
            return spanDiff;
        }

        const kindDiff = calendarKindRank(a.item.kind) - calendarKindRank(b.item.kind);
        if (kindDiff !== 0) {
            return kindDiff;
        }

        return String(a.item.title ?? '').localeCompare(String(b.item.title ?? ''), 'pt-BR');
    });

    /** @type {number[]} */
    const laneOccupiedUntil = [];
    /** @type {typeof candidates & { lane: number }[]} */
    const segments = [];
    const overflowKeys = new Set();

    for (const candidate of candidates) {
        let lane = laneOccupiedUntil.findIndex((until) => candidate.startCol > until);
        if (lane === -1) {
            if (laneOccupiedUntil.length >= maxLanes) {
                overflowKeys.add(candidate.key);
                continue;
            }
            lane = laneOccupiedUntil.length;
            laneOccupiedUntil.push(candidate.endCol);
        } else {
            laneOccupiedUntil[lane] = candidate.endCol;
        }

        segments.push({ ...candidate, lane });
    }

    const laneCount = laneOccupiedUntil.length;

    const spanningCols = Array.from({ length: 7 }, (_, col) =>
        segments.some((segment) => col >= segment.startCol && col <= segment.endCol),
    );

    const singleDayByCol = cells.map((cell) =>
        (cell?.items ?? []).filter((item) => !isSpanningCalendarEvent(item)),
    );

    const moreByCol = cells.map((_, col) => {
        let overflowMulti = 0;
        for (const key of overflowKeys) {
            const candidate = candidates.find((entry) => entry.key === key);
            if (candidate && col >= candidate.startCol && col <= candidate.endCol) {
                overflowMulti += 1;
            }
        }

        const singles = singleDayByCol[col] ?? [];
        const overflowSingle = Math.max(0, singles.length - maxSinglePerCell);

        return overflowMulti + overflowSingle;
    });

    return {
        segments,
        laneCount,
        singleDayByCol: singleDayByCol.map((items) => items.slice(0, maxSinglePerCell)),
        moreByCol,
        spanningCols,
    };
}

/**
 * @param {unknown} value
 * @returns {string|null}
 */
function sliceIso(value) {
    if (value == null) {
        return null;
    }

    return String(value).slice(0, 10);
}
