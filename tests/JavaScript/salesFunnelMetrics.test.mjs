import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    FUNNEL_MIN_WIDTH,
    buildFunnelRows,
    formatFunnelPercent,
} from '../../resources/js/utils/salesFunnelMetrics.js';

const stages = (leads, qualified, proposal, closed) => [
    { key: 'leads', count: leads },
    { key: 'qualified', count: qualified },
    { key: 'proposal', count: proposal },
    { key: 'closed', count: closed },
];

describe('formatFunnelPercent', () => {
    it('usa uma casa decimal abaixo de 10% e inteiro a partir de 10%, com vírgula', () => {
        assert.equal(formatFunnelPercent(3, 231), '1,3%');
        assert.equal(formatFunnelPercent(4, 231), '1,7%');
        assert.equal(formatFunnelPercent(2, 231), '0,9%');
        assert.equal(formatFunnelPercent(2, 4), '50%');
        assert.equal(formatFunnelPercent(4, 3), '133%');
        assert.equal(formatFunnelPercent(10, 10), '100%');
    });

    it('nunca mostra 0% com pelo menos 1 item', () => {
        assert.equal(formatFunnelPercent(1, 5000), '<0,1%');
        assert.equal(formatFunnelPercent(0, 231), '0%');
    });

    it('sem leads/base mostra "—"', () => {
        assert.equal(formatFunnelPercent(0, 0), '—');
        assert.equal(formatFunnelPercent(3, 0), '—');
    });

    it('9,96% arredonda para 10% (sem "10,0%")', () => {
        assert.equal(formatFunnelPercent(996, 10000), '10%');
    });
});

describe('buildFunnelRows', () => {
    it('caso real 231/3/4/2: % dos leads e % da etapa anterior', () => {
        const rows = buildFunnelRows(stages(231, 3, 4, 2));

        assert.deepEqual(rows.map((r) => r.pctOfLeads), ['100%', '1,3%', '1,7%', '0,9%']);
        assert.deepEqual(rows.map((r) => r.pctOfPrevious), [null, '1,3%', '133%', '50%']);
        assert.deepEqual(rows.map((r) => r.overPrevious), [false, false, true, false]);
    });

    it('0 leads: percentuais "—" e larguras no mínimo', () => {
        const rows = buildFunnelRows(stages(0, 0, 0, 0));

        assert.ok(rows.every((r) => r.pctOfLeads === '—'));
        assert.ok(rows.every((r) => r.topWidth === FUNNEL_MIN_WIDTH));
        assert.ok(rows.every((r) => r.overPrevious === false));
    });

    it('todas as etapas iguais: 100% em todas e funil sem estreitamento', () => {
        const rows = buildFunnelRows(stages(10, 10, 10, 10));

        assert.ok(rows.every((r) => r.pctOfLeads === '100%'));
        assert.ok(rows.every((r) => r.topWidth === 100 && r.bottomWidth === 100));
    });

    it('etapa anterior zerada com etapa seguinte > 0: sem % anterior e sem alerta', () => {
        const rows = buildFunnelRows(stages(50, 0, 4, 1));

        assert.equal(rows[2].pctOfPrevious, '—');
        assert.equal(rows[2].overPrevious, false);
        assert.equal(rows[3].pctOfPrevious, '25%');
    });

    it('1 item entre milhares: "<0,1%" dos leads', () => {
        const rows = buildFunnelRows(stages(5000, 1, 1, 1));

        assert.equal(rows[1].pctOfLeads, '<0,1%');
    });

    it('largura proporcional à contagem, com mínimo e continuidade entre faixas', () => {
        const rows = buildFunnelRows(stages(200, 100, 50, 2));

        assert.deepEqual(rows.map((r) => r.topWidth), [100, 50, 25, FUNNEL_MIN_WIDTH]);
        for (let i = 0; i < rows.length - 1; i += 1) {
            assert.equal(rows[i].bottomWidth, rows[i + 1].topWidth);
        }
        assert.equal(rows[3].bottomWidth, rows[3].topWidth);
    });

    it('contagem maior que os leads (dado inconsistente) não passa de 100% de largura', () => {
        const rows = buildFunnelRows(stages(2, 1, 8, 1));

        assert.ok(rows.every((r) => r.topWidth <= 100 && r.topWidth >= FUNNEL_MIN_WIDTH));
        assert.equal(rows[2].pctOfLeads, '400%');
    });

    it('abas Neste mês e Total usam a mesma definição, só mudam as contagens', () => {
        const month = buildFunnelRows(stages(40, 4, 6, 1));
        const total = buildFunnelRows(stages(231, 3, 4, 2));

        assert.deepEqual(month.map((r) => r.key), total.map((r) => r.key));
        assert.equal(month[1].pctOfLeads, '10%');
        assert.equal(total[1].pctOfLeads, '1,3%');
    });

    it('entrada vazia ou inválida não quebra', () => {
        assert.deepEqual(buildFunnelRows([]), []);
        assert.deepEqual(buildFunnelRows(null), []);
    });
});
