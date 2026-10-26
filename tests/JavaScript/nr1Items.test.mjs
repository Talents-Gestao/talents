import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    INITIAL_VISIBLE,
    filterItems,
    normalizeText,
    prepareItems,
    sortItems,
} from '../../resources/js/utils/nr1Items.js';

const makeItems = () =>
    Array.from({ length: 39 }, (_, index) => ({
        id: 100 + index,
        position: index + 1,
        section_id: Math.floor(index / 6) + 1,
        section_title: index < 6 ? 'Demandas e Sobrecarga' : `Dimensão ${Math.floor(index / 6) + 1}`,
        body: index === 6 ? 'Recebo reconhecimento pelo meu trabalho.' : `Pergunta número ${index + 1}`,
        favorable_pct: (index * 7) % 100,
        unfavorable_pct: (index * 13) % 100,
    }));

describe('nr1Items (T15)', () => {
    it('normaliza acentos e caixa para a busca', () => {
        assert.equal(normalizeText('  Reconhecimento Á  '), 'reconhecimento a');
    });

    it('mostra 12 cartões de início e todos os 39 com "ver todas"', () => {
        const items = makeItems();
        const initial = prepareItems(items);
        assert.equal(initial.shown.length, INITIAL_VISIBLE);
        assert.equal(initial.total, 39);
        assert.equal(initial.hiddenCount, 27);

        const all = prepareItems(items, { showAll: true });
        assert.equal(all.shown.length, 39);
        assert.equal(all.hiddenCount, 0);
    });

    it('busca por texto do enunciado ou da dimensão, sem diferenciar acento', () => {
        const items = makeItems();
        assert.deepEqual(filterItems(items, { query: 'RECONHECIMENTO' }).map((i) => i.position), [7]);
        assert.equal(filterItems(items, { query: 'sobrecarga' }).length, 6);
        assert.equal(filterItems(items, { query: 'nada disso existe' }).length, 0);
    });

    it('filtra por dimensão e combina com a busca', () => {
        const items = makeItems();
        assert.equal(filterItems(items, { sectionId: 2 }).length, 6);
        assert.equal(filterItems(items, { sectionId: '2' }).length, 6);
        assert.equal(filterItems(items, { sectionId: null }).length, 39);
        assert.deepEqual(filterItems(items, { sectionId: 2, query: 'reconhecimento' }).map((i) => i.position), [7]);
        assert.equal(filterItems(items, { sectionId: 1, query: 'reconhecimento' }).length, 0);
    });

    it('ordena por ordem das perguntas, mais favoráveis e mais pontos de atenção', () => {
        const shuffled = makeItems().reverse();
        assert.deepEqual(sortItems(shuffled, 'order').map((i) => i.position), Array.from({ length: 39 }, (_, i) => i + 1));

        const favorable = sortItems(shuffled, 'favorable').map((i) => i.favorable_pct);
        assert.deepEqual(favorable, [...favorable].sort((a, b) => b - a));

        const attention = sortItems(shuffled, 'attention').map((i) => i.unfavorable_pct);
        assert.deepEqual(attention, [...attention].sort((a, b) => b - a));
    });

    it('empates seguem a ordem das perguntas e itens sem respostas vão para o fim', () => {
        const items = [
            { id: 1, position: 1, section_id: 1, section_title: 'A', body: 'a', favorable_pct: null, unfavorable_pct: null },
            { id: 2, position: 2, section_id: 1, section_title: 'A', body: 'b', favorable_pct: 50, unfavorable_pct: 10 },
            { id: 3, position: 3, section_id: 1, section_title: 'A', body: 'c', favorable_pct: 50, unfavorable_pct: 10 },
            { id: 4, position: 4, section_id: 1, section_title: 'A', body: 'd', favorable_pct: 80, unfavorable_pct: 5 },
        ];
        assert.deepEqual(sortItems(items, 'favorable').map((i) => i.id), [4, 2, 3, 1]);
        assert.deepEqual(sortItems(items, 'attention').map((i) => i.id), [2, 3, 4, 1]);
    });

    it('não altera a lista original', () => {
        const items = makeItems();
        const before = items.map((i) => i.id);
        sortItems(items, 'favorable');
        prepareItems(items, { sort: 'attention', query: 'pergunta' });
        assert.deepEqual(items.map((i) => i.id), before);
    });

    it('vazio: lista vazia não quebra e informa total zero', () => {
        const result = prepareItems([], { query: 'x' });
        assert.deepEqual(result, { shown: [], total: 0, hiddenCount: 0 });
    });
});
