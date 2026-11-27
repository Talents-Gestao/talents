import assert from 'node:assert/strict';
import test from 'node:test';

import { reopenCopy, versionUpdateSummary } from '../../resources/js/utils/feedbackSessionVersion.js';

test('prévia da atualização de versão: mantidas, novas e que saem, com as respostas', () => {
    assert.equal(versionUpdateSummary(null), null);

    const summary = versionUpdateSummary({
        to: { number: 3 },
        kept: [{}, {}],
        added: [{}],
        removed: [{}, {}, {}],
        answers_moved: 1,
        answers_out_of_version: 2,
    });
    assert.equal(summary.title, 'Atualizar para a versão 3');
    assert.deepEqual(summary.lines, [
        '2 perguntas mantidas (1 resposta preservada)',
        '1 pergunta nova',
        '3 perguntas saem da versão',
    ]);
    assert.match(summary.warning, /^2 respostas ficam fora da versão atual: continuam guardadas e visíveis/);
});

test('sem resposta que sai, não há aviso; singular correto', () => {
    const summary = versionUpdateSummary({ to: { number: 2 }, kept: [{}], added: [], removed: [{}], answers_moved: 1, answers_out_of_version: 0 });
    assert.equal(summary.warning, null);
    assert.deepEqual(summary.lines, ['1 pergunta mantida (1 resposta preservada)', '0 perguntas novas', '1 pergunta sai da versão']);

    const one = versionUpdateSummary({ to: { number: 2 }, kept: [], added: [], removed: [{}], answers_moved: 0, answers_out_of_version: 1 });
    assert.match(one.warning, /^1 resposta fica fora da versão atual: continua guardada e visível/);
});

test('reabrir para edição: convites cancelados e assinaturas descartadas', () => {
    const none = reopenCopy({ invites: 2, signed: 0 });
    assert.match(none.message, /^O feedback volta para «Em preenchimento» e 2 convites de assinatura são cancelados\. Nenhuma assinatura/);
    assert.equal(none.checkLabel, 'Entendo que os convites serão cancelados.');

    const one = reopenCopy({ invites: 2, signed: 1 });
    assert.match(one.message, /1 assinatura já coletada será descartada\./);
    assert.match(one.checkLabel, /e 1 assinatura será descartada\.$/);

    assert.match(reopenCopy({ invites: 2, signed: 2 }).checkLabel, /2 assinaturas serão descartadas/);
});
