import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    buildLedgerRows,
    defaultSortForStatus,
    isListNavigation,
    readGroupPreference,
    saoPauloIsoDay,
    todayIsoInSaoPaulo,
    writeGroupPreference,
} from '../../resources/js/utils/financeLedger.js';

const memoryStorage = () => {
    const data = new Map();
    return {
        getItem: (k) => (data.has(k) ? data.get(k) : null),
        setItem: (k, v) => data.set(k, String(v)),
    };
};

const throwingStorage = {
    getItem() {
        throw new Error('SecurityError');
    },
    setItem() {
        throw new Error('QuotaExceededError');
    },
};

describe('financeLedger', () => {
    it('lista corrida: cada linha é avulsa, mesmo parcelas da mesma venda', () => {
        const rows = buildLedgerRows(
            [
                { id: 'sale-1', source: 'sale', sale_id: 7 },
                { id: 'manual-2', source: 'manual', sale_id: null },
                { id: 'sale-3', source: 'sale', sale_id: 7 },
            ],
            false,
        );
        assert.deepEqual(
            rows.map((r) => [r.item.id, r.groupStart, r.groupSize]),
            [
                ['sale-1', true, 1],
                ['manual-2', true, 1],
                ['sale-3', true, 1],
            ],
        );
    });

    it('agrupado: parcelas consecutivas da mesma venda formam um bloco', () => {
        const rows = buildLedgerRows(
            [
                { id: 'sale-1', source: 'sale', sale_id: 7 },
                { id: 'sale-3', source: 'sale', sale_id: 7 },
                { id: 'manual-2', source: 'manual', sale_id: null },
            ],
            true,
        );
        assert.deepEqual(
            rows.map((r) => [r.item.id, r.groupStart, r.groupSize]),
            [
                ['sale-1', true, 2],
                ['sale-3', false, 2],
                ['manual-2', true, 1],
            ],
        );
    });

    it('ordem padrão por aba', () => {
        assert.equal(defaultSortForStatus('paid'), 'paid_at');
        assert.equal(defaultSortForStatus('pending'), 'due_date');
        assert.equal(defaultSortForStatus(''), 'due_date');
        assert.equal(defaultSortForStatus(undefined), 'due_date');
    });

    it('dia de pagamento no calendário de São Paulo', () => {
        assert.equal(saoPauloIsoDay('2026-08-12'), '2026-08-12');
        assert.equal(saoPauloIsoDay('2026-08-12 00:00:00'), '2026-08-12');
        // 00:00 em SP serializado em UTC continua sendo o dia 12.
        assert.equal(saoPauloIsoDay('2026-08-12T03:00:00.000000Z'), '2026-08-12');
        // 23:30 de 31/07 em SP = 02:30Z de 01/08: o dia é 31/07.
        assert.equal(saoPauloIsoDay('2026-08-01T02:30:00Z'), '2026-07-31');
        assert.equal(saoPauloIsoDay(null), '');
        assert.equal(saoPauloIsoDay('não é data'), '');
    });

    it('«hoje» segue São Paulo, não o fuso do navegador', () => {
        assert.equal(todayIsoInSaoPaulo(new Date('2026-08-21T02:00:00Z')), '2026-08-20');
        assert.equal(todayIsoInSaoPaulo(new Date('2026-08-21T03:00:00Z')), '2026-08-21');
    });

    it('carregando só nas visitas GET da própria lista', () => {
        const here = 'http://localhost:8080/admin/financeiro/contas-a-receber?group=sale';
        const url = (s) => new URL(s, here);
        assert.equal(isListNavigation({ method: 'get', url: url('/admin/financeiro/contas-a-receber?page=2&group=sale') }, here), true);
        assert.equal(isListNavigation({ method: 'GET', url: url('/admin/financeiro/contas-a-receber') }, here), true);
        // Envio de formulário (modais) e navegação para outra tela não contam.
        assert.equal(isListNavigation({ method: 'patch', url: url('/admin/financeiro/parcelas/1') }, here), false);
        assert.equal(isListNavigation({ method: 'put', url: url('/admin/financeiro/contas-a-receber/5') }, here), false);
        assert.equal(isListNavigation({ method: 'get', url: url('/admin/financeiro/vendas/17') }, here), false);
        assert.equal(isListNavigation(null, here), false);
    });

    it('preferência de agrupamento sobrevive sem storage', () => {
        const storage = memoryStorage();
        assert.equal(readGroupPreference(storage), null);
        writeGroupPreference(true, storage);
        assert.equal(readGroupPreference(storage), true);
        writeGroupPreference(false, storage);
        assert.equal(readGroupPreference(storage), false);

        assert.equal(readGroupPreference(throwingStorage), null);
        assert.doesNotThrow(() => writeGroupPreference(true, throwingStorage));
        assert.equal(readGroupPreference(null), null);
    });
});
