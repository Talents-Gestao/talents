import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';

import { dueDateChanged, replacedFields, suggestionValues } from '../../resources/js/utils/nr1MeasureSuggestion.js';

const measure = {
    factor_label: 'Demandas e Sobrecarga',
    measure_description: 'Revisar metas.',
    objective: '',
    expected_result: null,
    indicator: 'Percentual de equipes',
    suggested: { responsible: 'Gestão de Operações', due_date: '2026-12-30', due_origin: 'talents', due_note: 'Faixa da dimensão: Atenção.' },
};

describe('sugestão de medida: justificativa da alteração', () => {
    it('preencher campo vazio não exige justificativa', () => {
        const current = { ...suggestionValues(measure), objective: 'Novo objetivo', expected_result: 'Resultado' };
        assert.deepEqual(replacedFields(suggestionValues(measure), current), []);
    });

    it('trocar ou apagar valor já registrado exige justificativa', () => {
        const current = { ...suggestionValues(measure), indicator: 'Outro indicador', responsible_text: '' };
        assert.deepEqual(replacedFields(suggestionValues(measure), current), ['indicator', 'responsible_text']);
    });

    it('espaços em volta não contam como troca', () => {
        const current = { ...suggestionValues(measure), measure_description: '  Revisar metas.  ' };
        assert.deepEqual(replacedFields(suggestionValues(measure), current), []);
    });
});

describe('sugestão de medida: origem e justificativa do prazo', () => {
    it('só ficam editáveis quando o prazo sugerido muda', () => {
        const recorded = suggestionValues(measure);
        assert.equal(dueDateChanged(recorded, { ...recorded }), false);
        assert.equal(dueDateChanged(recorded, { ...recorded, suggested_due_date: '2027-01-15' }), true);
    });

    it('o cartão mostra a justificativa só quando necessária e o prazo como informação automática', () => {
        const card = readFileSync(new URL('../../resources/js/Components/Nr1Shell/MeasureCard.vue', import.meta.url), 'utf8');
        assert.match(card, /<div v-if="needsReason"[^>]*data-measure-reason>/);
        assert.match(card, /<template v-if="dueEditable">/);
        assert.match(card, /<details v-else[^>]*data-measure-due-info>/);
        assert.match(card, /reason: needsReason\.value \? data\.reason \|\| null : null/);
    });
});
