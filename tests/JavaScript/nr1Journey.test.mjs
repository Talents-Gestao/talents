import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { buildSteps, firstStepOfSection, progressPercent, unansweredIn } from '../../resources/js/utils/nr1Journey.js';

const sections = () => {
    let id = 0;

    return Array.from({ length: 7 }, (_, index) => ({
        id: index + 1,
        title: `Dimensão ${index + 1}`,
        description: `Descrição ${index + 1}`,
        questions: Array.from({ length: index === 6 ? 3 : 6 }, () => ({ id: ++id, body: `Afirmação ${id}` })),
    }));
};

describe('nr1Journey', () => {
    it('V11 tem uma afirmação por etapa: 39 etapas, numeradas de 1 a 39', () => {
        const steps = buildSteps(sections(), true);

        assert.equal(steps.length, 39);
        assert.ok(steps.every((step) => step.questions.length === 1));
        assert.deepEqual([steps[0].ordinal, steps[38].ordinal], [1, 39]);
        assert.equal(steps[0].sectionTitle, 'Dimensão 1');
        assert.equal(steps[38].sectionTitle, 'Dimensão 7');
    });

    it('campanha antiga mantém uma dimensão por etapa', () => {
        const steps = buildSteps(sections(), false);

        assert.equal(steps.length, 7);
        assert.equal(steps[0].questions.length, 6);
        assert.equal(steps[0].ordinal, null);
        assert.equal(steps[0].description, 'Descrição 1');
    });

    it('dimensão sem perguntas não vira etapa', () => {
        const list = sections();
        list[2].questions = [];

        assert.equal(buildSteps(list, true).length, 33);
    });

    it('não avança sem resposta e reconhece resposta 1 a 5', () => {
        const steps = buildSteps(sections(), true);
        const answers = { 1: null, 2: 3 };

        assert.equal(unansweredIn(steps[0], answers).length, 1);
        assert.equal(unansweredIn(steps[1], answers).length, 0);
        assert.equal(unansweredIn(steps[2], {}).length, 1);
    });

    it('«Rever» aponta para a primeira etapa da dimensão', () => {
        const steps = buildSteps(sections(), true);

        assert.equal(firstStepOfSection(steps, 1), 0);
        assert.equal(firstStepOfSection(steps, 2), 6);
        assert.equal(firstStepOfSection(steps, 99), -1);
    });

    it('progresso em % pelas respostas dadas', () => {
        assert.equal(progressPercent({}), 0);
        assert.equal(progressPercent({ 1: 1, 2: null, 3: 5, 4: '' }), 50);
    });
});
