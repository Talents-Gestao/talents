import { isMultiDayRange } from '@/utils/strategicCalendarDate';

/**
 * @param {Record<string, unknown>} item
 * @returns {boolean}
 */
export function isSpanningCalendarEvent(item) {
    const start = sliceIso(item?.range_starts_on || item?.occurs_on);
    const end = sliceIso(item?.ends_on || item?.occurs_on);

    return isMultiDayRange(start, end);
}

/**
 * @param {Record<string, unknown>} item
 * @returns {string}
 */
export function spanningEventKey(item) {
    const start = sliceIso(item?.range_starts_on || item?.occurs_on);
    const end = sliceIso(item?.ends_on || item?.occurs_on);
    const source = item?.source_id ?? item?.id;

    return `${source}|${start}|${end}`;
}

/**
 * Empacota eventos multi-dia de uma semana (7 células) em lanes horizontais estilo Google Agenda.
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
 * }}
 */
export function packWeekSpanningSegments(weekCells, options = {}) {
    const maxLanes = options.maxLanes ?? 3;
    const maxSinglePerCell = options.maxSinglePerCell ?? 2;
    const cells = Array.isArray(weekCells) ? weekCells : [];

    const firstIso = sliceIso(cells[0]?.iso);
    const lastIso = sliceIso(cells[6]?.iso);

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
            const rangeEnd = sliceIso(item.ends_on || item.occurs_on);
            if (!rangeStart || !rangeEnd) {
                continue;
            }

            let startCol = -1;
            let endCol = -1;
            for (let col = 0; col < 7; col += 1) {
                const iso = sliceIso(cells[col]?.iso);
                if (!iso || iso < rangeStart || iso > rangeEnd) {
                    continue;
                }
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

    candidates.sort(
        (a, b) =>
            a.startCol - b.startCol ||
            b.span - a.span ||
            String(a.item.title ?? '').localeCompare(String(b.item.title ?? ''), 'pt-BR'),
    );

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
