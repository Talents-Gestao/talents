import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';

import { applyAutoGrow, computeAutoGrowHeight } from '../../resources/js/utils/autoGrowTextarea.js';

describe('computeAutoGrowHeight', () => {
    it('acompanha o conteúdo entre o mínimo e o máximo, sem rolagem enquanto couber', () => {
        assert.deepEqual(computeAutoGrowHeight({ contentHeight: 300, minHeight: 200, maxHeight: 600 }), { height: 300, overflowY: 'hidden' });
    });

    it('nunca fica abaixo do mínimo', () => {
        assert.deepEqual(computeAutoGrowHeight({ contentHeight: 40, minHeight: 200, maxHeight: 600 }), { height: 200, overflowY: 'hidden' });
    });

    it('para no máximo e liga a rolagem interna', () => {
        assert.deepEqual(computeAutoGrowHeight({ contentHeight: 900, minHeight: 200, maxHeight: 600 }), { height: 600, overflowY: 'auto' });
    });

    it('altura arrastada pela alça vira o piso e pode passar do máximo', () => {
        assert.deepEqual(computeAutoGrowHeight({ contentHeight: 100, minHeight: 200, maxHeight: 600, userHeight: 700 }), { height: 700, overflowY: 'hidden' });
        assert.deepEqual(computeAutoGrowHeight({ contentHeight: 900, minHeight: 200, maxHeight: 600, userHeight: 300 }), { height: 600, overflowY: 'auto' });
    });

    it('sem máximo (campo desabilitado) mostra o texto inteiro', () => {
        assert.deepEqual(computeAutoGrowHeight({ contentHeight: 5000, minHeight: 200 }), { height: 5000, overflowY: 'hidden' });
    });
});

/** Textarea falso: scrollHeight = maior entre conteúdo e altura atual, como no navegador. */
function fakeTextarea({ contentPx, disabled = false }) {
    const el = {
        style: {},
        matches: (selector) => selector === ':disabled' && disabled,
        get scrollHeight() {
            const current = Number.parseFloat(this.style.height) || 0;
            // border-box: style.height inclui borda (2px); scrollHeight inclui só conteúdo + padding.
            return Math.max(contentPx, current - 2);
        },
    };
    return el;
}

const win = {
    innerHeight: 1000,
    getComputedStyle: () => ({
        lineHeight: '20px',
        fontSize: '14px',
        paddingTop: '10px',
        paddingBottom: '10px',
        borderTopWidth: '1px',
        borderBottomWidth: '1px',
        boxSizing: 'border-box',
    }),
};

describe('applyAutoGrow', () => {
    // 8 linhas × 20px + padding 20 + borda 2 = 182px de mínimo; máximo 60vh = 600px.
    it('texto curto fica no mínimo de 8 linhas, sem rolagem', () => {
        const el = fakeTextarea({ contentPx: 60 });
        assert.equal(applyAutoGrow(el, { minRows: 8, maxVh: 60, win }), 182);
        assert.equal(el.style.height, '182px');
        assert.equal(el.style.overflowY, 'hidden');
    });

    it('texto longo cresce com o conteúdo (conteúdo + borda)', () => {
        const el = fakeTextarea({ contentPx: 400 });
        assert.equal(applyAutoGrow(el, { minRows: 8, maxVh: 60, win }), 402);
        assert.equal(el.style.overflowY, 'hidden');
    });

    it('texto muito longo para em 60vh e rola por dentro', () => {
        const el = fakeTextarea({ contentPx: 2000 });
        assert.equal(applyAutoGrow(el, { minRows: 8, maxVh: 60, win }), 600);
        assert.equal(el.style.overflowY, 'auto');
    });

    it('texto que diminui faz o campo encolher (mede a partir do mínimo, não da altura antiga)', () => {
        const el = fakeTextarea({ contentPx: 400 });
        applyAutoGrow(el, { minRows: 8, maxVh: 60, win });
        const shrunk = fakeTextarea({ contentPx: 250 });
        shrunk.style.height = el.style.height;
        assert.equal(applyAutoGrow(shrunk, { minRows: 8, maxVh: 60, win }), 252);
    });

    it('desabilitado (modelo travado ou modo leitura) mostra o texto inteiro, sem cortar em 60vh', () => {
        const el = fakeTextarea({ contentPx: 2000, disabled: true });
        assert.equal(applyAutoGrow(el, { minRows: 8, maxVh: 60, win }), 2002);
        assert.equal(el.style.overflowY, 'hidden');
    });
});

describe('uso do componente nos formulários de Feedbacks', () => {
    const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
    const read = (path) => readFileSync(join(root, path), 'utf8');

    it('nenhum <textarea> solto sobra nos formulários de modelo e de sessão', () => {
        for (const file of [
            'resources/js/Pages/Client/Feedbacks/Templates/Form.vue',
            'resources/js/Components/Feedback/FeedbackQuestionField.vue',
            'resources/js/Components/Feedback/FeedbackSectionExtraField.vue',
        ]) {
            assert.doesNotMatch(read(file), /<textarea\b/, file);
            assert.match(read(file), /<AutoGrowTextarea\b/, file);
        }
    });

    it('descrição do modelo e texto de orientação começam com 8 linhas', () => {
        const form = read('resources/js/Pages/Client/Feedbacks/Templates/Form.vue');
        assert.match(form, /<AutoGrowTextarea id="template_description" v-model="form\.description" :min-rows="8"/);
        assert.match(form, /:min-rows="section\.section_type === 'intro' \? 8 : 4"/);
    });
});
