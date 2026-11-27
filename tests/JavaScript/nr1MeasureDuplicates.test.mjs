import test from 'node:test';
import assert from 'node:assert/strict';
import { duplicatesHeadline, duplicatesOutcome, duplicatesSummary } from '../../resources/js/utils/nr1MeasureDuplicates.js';

const groups = [
    { dimension: 'Demandas e Sobrecarga', merged: true, kept: { id: 1 }, removed: [{ id: 2 }], manual: [] },
    { dimension: 'Esgotamento e Saúde Emocional', merged: false, kept: { id: 3 }, removed: [{ id: 4 }], manual: [{ id: 5 }] },
];

test('resume mantidas, juntadas, removidas e decisões manuais', () => {
    assert.deepEqual(duplicatesSummary(groups), { dimensions: 2, kept: 2, merged: 1, removed: 2, manual: 1, canApply: true });
    assert.equal(duplicatesOutcome(groups), '2 medidas mantidas · 1 com a ação juntada · 2 sugestões removidas · 1 medida requer decisão manual.');
    assert.equal(duplicatesHeadline(groups), '2 dimensões têm mais de uma medida no plano.');
});

test('sem nada removível não permite aplicar', () => {
    const manualOnly = [{ dimension: 'Liderança e Gestão', merged: false, kept: { id: 1 }, removed: [], manual: [{ id: 2 }] }];
    assert.equal(duplicatesSummary(manualOnly).canApply, false);
    assert.equal(duplicatesOutcome(manualOnly), '1 medida mantida · 0 sugestões removidas · 1 medida requer decisão manual.');
    assert.equal(duplicatesHeadline(manualOnly), '1 dimensão tem mais de uma medida no plano.');
});

test('entrada vazia ou inválida', () => {
    assert.equal(duplicatesHeadline([]), '');
    assert.deepEqual(duplicatesSummary(null), { dimensions: 0, kept: 0, merged: 0, removed: 0, manual: 0, canApply: false });
});
