import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    isSpanningCalendarEvent,
    packWeekSpanningSegments,
    spanningEventKey,
} from '../../resources/js/utils/strategicCalendarMonthLanes.js';

function weekCells(itemsByIso) {
    const isos = [
        '2026-07-12',
        '2026-07-13',
        '2026-07-14',
        '2026-07-15',
        '2026-07-16',
        '2026-07-17',
        '2026-07-18',
    ];

    return isos.map((iso) => ({
        iso,
        items: itemsByIso[iso] ?? [],
    }));
}

function leaveOnDays(days, overrides = {}) {
    const leave = {
        id: 'leave-9-2026-07-13',
        source_id: 9,
        kind: 'leave',
        title: 'Férias — Bruno Lima',
        range_starts_on: '2026-07-13',
        ends_on: '2026-07-31',
        ...overrides,
    };

    /** @type {Record<string, Array<Record<string, unknown>>>} */
    const map = {};
    for (const iso of days) {
        map[iso] = [{ ...leave, id: `leave-9-${iso}`, occurs_on: iso }];
    }

    return map;
}

describe('strategicCalendarMonthLanes', () => {
    it('detecta span multi-dia e chave estável por source+intervalo', () => {
        const item = {
            source_id: 9,
            occurs_on: '2026-07-20',
            range_starts_on: '2026-07-13',
            ends_on: '2026-07-31',
            kind: 'leave',
        };

        assert.equal(isSpanningCalendarEvent(item), true);
        assert.equal(spanningEventKey(item), '9|2026-07-13|2026-07-31');
        assert.equal(isSpanningCalendarEvent({ ...item, ends_on: null }), false);
    });

    it('deduplica ocorrências diárias numa única tirinha na semana', () => {
        const packed = packWeekSpanningSegments(
            weekCells(leaveOnDays([
                '2026-07-13',
                '2026-07-14',
                '2026-07-15',
                '2026-07-16',
                '2026-07-17',
                '2026-07-18',
            ])),
            { maxLanes: 5 },
        );

        assert.equal(packed.segments.length, 1);
        assert.equal(packed.segments[0].startCol, 1);
        assert.equal(packed.segments[0].endCol, 6);
        assert.equal(packed.segments[0].continuesBefore, false);
        assert.equal(packed.segments[0].continuesAfter, true);
        assert.equal(packed.laneCount, 1);
    });

    it('prioriza continuação da semana anterior para não “furar” o intervalo', () => {
        const middleWeekIsos = [
            '2026-07-19',
            '2026-07-20',
            '2026-07-21',
            '2026-07-22',
            '2026-07-23',
            '2026-07-24',
            '2026-07-25',
        ];

        const leaveItems = {};
        for (const iso of middleWeekIsos) {
            leaveItems[iso] = [
                {
                    id: `leave-9-${iso}`,
                    source_id: 9,
                    kind: 'leave',
                    title: 'Férias — Bruno Lima',
                    occurs_on: iso,
                    range_starts_on: '2026-07-13',
                    ends_on: '2026-07-31',
                },
            ];
        }

        // Evento curto novo na semana do meio (kind com rank melhor que leave).
        leaveItems['2026-07-19'].push({
            id: 'evt-1-2026-07-19',
            source_id: 1,
            kind: 'event',
            title: 'Compromisso curto',
            occurs_on: '2026-07-19',
            range_starts_on: '2026-07-19',
            ends_on: '2026-07-20',
        });
        leaveItems['2026-07-20'].push({
            id: 'evt-1-2026-07-20',
            source_id: 1,
            kind: 'event',
            title: 'Compromisso curto',
            occurs_on: '2026-07-20',
            range_starts_on: '2026-07-19',
            ends_on: '2026-07-20',
        });

        const cells = middleWeekIsos.map((iso) => ({
            iso,
            items: leaveItems[iso],
        }));

        const packed = packWeekSpanningSegments(cells, { maxLanes: 1 });

        assert.equal(packed.segments.length, 1);
        assert.equal(packed.segments[0].item.kind, 'leave');
        assert.equal(packed.segments[0].continuesBefore, true);
        assert.equal(packed.segments[0].startCol, 0);
        assert.equal(packed.segments[0].endCol, 6);
        assert.equal(packed.moreByCol[0] > 0, true);
    });

    it('coloca evento acima de férias quando ambos começam na mesma coluna', () => {
        const cells = weekCells({
            '2026-07-13': [
                {
                    id: 'leave-1-2026-07-13',
                    source_id: 10,
                    kind: 'leave',
                    title: 'Férias — Carla',
                    occurs_on: '2026-07-13',
                    range_starts_on: '2026-07-13',
                    ends_on: '2026-07-15',
                },
                {
                    id: 'evt-2-2026-07-13',
                    source_id: 2,
                    kind: 'event',
                    title: 'sASA',
                    occurs_on: '2026-07-13',
                    range_starts_on: '2026-07-13',
                    ends_on: '2026-07-15',
                },
            ],
            '2026-07-14': [
                {
                    id: 'leave-1-2026-07-14',
                    source_id: 10,
                    kind: 'leave',
                    title: 'Férias — Carla',
                    occurs_on: '2026-07-14',
                    range_starts_on: '2026-07-13',
                    ends_on: '2026-07-15',
                },
                {
                    id: 'evt-2-2026-07-14',
                    source_id: 2,
                    kind: 'event',
                    title: 'sASA',
                    occurs_on: '2026-07-14',
                    range_starts_on: '2026-07-13',
                    ends_on: '2026-07-15',
                },
            ],
            '2026-07-15': [
                {
                    id: 'leave-1-2026-07-15',
                    source_id: 10,
                    kind: 'leave',
                    title: 'Férias — Carla',
                    occurs_on: '2026-07-15',
                    range_starts_on: '2026-07-13',
                    ends_on: '2026-07-15',
                },
                {
                    id: 'evt-2-2026-07-15',
                    source_id: 2,
                    kind: 'event',
                    title: 'sASA',
                    occurs_on: '2026-07-15',
                    range_starts_on: '2026-07-13',
                    ends_on: '2026-07-15',
                },
            ],
        });

        const packed = packWeekSpanningSegments(cells, { maxLanes: 5 });

        assert.equal(packed.segments.length, 2);
        assert.equal(packed.segments[0].item.kind, 'event');
        assert.equal(packed.segments[0].lane, 0);
        assert.equal(packed.segments[1].item.kind, 'leave');
        assert.equal(packed.segments[1].lane, 1);
    });
});
