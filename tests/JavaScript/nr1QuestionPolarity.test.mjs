import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';
import { POLARITY, polarityLegend, questionPolarity } from '../../resources/js/Components/Nr1Shell/nr1QuestionPolarity.js';

const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
const panel = readFileSync(join(root, 'resources/js/Components/Nr1SurveyResultsPanel.vue'), 'utf8');

describe('Detalhamento por pergunta: cor pela polaridade (reverse_score)', () => {
    it('reverse_score = true é favorável (verde)', () => {
        const p = questionPolarity({ reverse_score: true });
        assert.equal(p.key, 'favorable');
        assert.equal(p.bar, 'bg-emerald-500');
        assert.equal(p.label, 'Sentido favorável');
    });

    it('reverse_score = false é desfavorável (tom de alerta)', () => {
        const p = questionPolarity({ reverse_score: false });
        assert.equal(p.key, 'unfavorable');
        assert.equal(p.bar, 'bg-amber-500');
        assert.equal(questionPolarity({}).key, 'unfavorable');
    });

    it('instrumento com polaridade pendente de auditoria: cinza neutro com selo, para qualquer pergunta', () => {
        for (const reverse of [true, false]) {
            const p = questionPolarity({ reverse_score: reverse }, { polarityPending: true });
            assert.equal(p.key, 'pending');
            assert.equal(p.bar, 'bg-slate-400');
            assert.equal(p.label, 'Polaridade pendente de auditoria');
        }
    });

    it('empresa com polaridade em auditoria: cinza neutro, sem selo e sem legenda', () => {
        const options = { polarityPending: true, showAuditBadge: false };
        for (const reverse of [true, false]) {
            const p = questionPolarity({ reverse_score: reverse }, options);
            assert.equal(p.key, 'neutral');
            assert.equal(p.bar, 'bg-slate-400');
            assert.equal(p.label, null);
        }
        assert.deepEqual(polarityLegend(options), []);
        // Sem auditoria pendente, a empresa vê as mesmas cores da Talents.
        assert.equal(questionPolarity({ reverse_score: true }, { polarityPending: false, showAuditBadge: false }).key, 'favorable');
    });

    it('legenda curta acompanha o estado', () => {
        assert.deepEqual(polarityLegend().map((i) => i.key), ['favorable', 'unfavorable']);
        assert.deepEqual(polarityLegend({ polarityPending: true }).map((i) => i.key), ['pending']);
        assert.ok(Object.values(POLARITY).every((i) => i.legend === null || i.legend.length < 80));
    });

    it('o painel usa o helper (sem regra de polaridade duplicada) e mostra legenda e selo', () => {
        assert.match(panel, /questionPolarity\(question, polarityOptions\)/);
        assert.match(panel, /polarityLegend\(polarityOptions\)/);
        assert.match(panel, /polarityPending: \{ type: Boolean, default: false \}/);
        assert.match(panel, /showPolarityAudit: \{ type: Boolean, default: false \}/);
        assert.match(panel, /v-if="questionPolarity\(question, polarityOptions\)\.label"/);
        assert.doesNotMatch(panel, /const optionBarClass/);
    });
});
