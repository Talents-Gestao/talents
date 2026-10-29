import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { overviewCardsText } from '../../resources/js/Components/Nr1Shell/nr1OverviewCards.js';

const base = {
    participation: { hidden: false, respondents: 40, eligible: 48, percent_label: '83,3%' },
    attention: { count: 6, total: 7 },
    plan: { confirmed: 0, total: 6 },
};

describe('cartões da Visão geral', () => {
    it('elegíveis vêm do cadastro; sem cadastro, diz que não foram informados', () => {
        assert.equal(overviewCardsText(base).eligible, '48 elegíveis');
        assert.equal(overviewCardsText({ ...base, participation: { ...base.participation, eligible: null } }).eligible, 'elegíveis não informados');
    });

    it('«Todas aparecem nas prioridades» só quando a lista mostra todas as dimensões em atenção', () => {
        assert.equal(overviewCardsText(base, 6).attention, 'Todas aparecem nas prioridades');
        assert.equal(overviewCardsText(base, 5).attention, null);
        assert.equal(overviewCardsText(base).attention, null);
        assert.equal(overviewCardsText({ ...base, attention: { count: 0, total: 7 } }, 0).attention, 'Nenhuma dimensão em atenção');
    });

    it('plano com medidas mostra o progresso; sem medidas, estado honesto no lugar de 0 / 0', () => {
        assert.deepEqual(overviewCardsText(base).plan, { progress: 0 });
        assert.deepEqual(overviewCardsText({ ...base, plan: { confirmed: 3, total: 6 } }).plan, { progress: 50 });
        const empty = overviewCardsText({ ...base, plan: { confirmed: 0, total: 0 } });
        assert.equal(empty.plan, null);
        assert.equal(empty.planEmpty, 'Plano de ação ainda não elaborado.');
        assert.equal(overviewCardsText({ ...base, plan: null, planMessage: 'O plano aparece quando a Talents publicar o parecer.' }).planEmpty, 'O plano aparece quando a Talents publicar o parecer.');
    });
});
