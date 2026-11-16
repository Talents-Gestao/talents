import assert from 'node:assert/strict';
import test from 'node:test';

import {
    ensureTwoOptions,
    normalizeTemplateOptions,
    templateFormPayload,
    templateLockMessage,
} from '../../resources/js/utils/feedbackTemplateForm.js';

const data = {
    title: 'Alinhamento',
    description: 'Uso interno',
    is_active: true,
    sections: [{ title: 'Resultados', description: '', questions: [{ body: 'O que foi entregue?' }] }],
};

test('modelo livre envia nome, descrição, status e a estrutura completa', () => {
    assert.deepEqual(templateFormPayload(data, false), data);
});

test('modelo em uso envia só nome, descrição e status (seções ficam de fora)', () => {
    assert.deepEqual(templateFormPayload(data, true), { title: 'Alinhamento', description: 'Uso interno', is_active: true });
});

test('motivo do bloqueio cita o uso, explica a regra e aponta para duplicar', () => {
    const one = templateLockMessage(1);
    assert.match(one, /usado em 1 feedback,/);
    assert.match(one, /respostas registradas dependem destas perguntas/);
    assert.match(one, /nome, descrição e «Ativo» continuam editáveis/);
    assert.match(one, /duplique o modelo e edite a cópia/);

    assert.match(templateLockMessage(22), /usado em 22 feedbacks/);
    assert.match(templateLockMessage(undefined), /usado em feedbacks/);
});

test('opções viram {value, label} preservando o valor existente', () => {
    assert.deepEqual(normalizeTemplateOptions(['Bom', { value: 'abaixo', label: 'Abaixo' }, null]), [
        { value: '', label: 'Bom' },
        { value: 'abaixo', label: 'Abaixo' },
        { value: '', label: '' },
    ]);
    assert.deepEqual(normalizeTemplateOptions(undefined), []);
});

test('escolha única recebe ao menos duas opções sem perder as existentes', () => {
    assert.deepEqual(ensureTwoOptions([]), [
        { value: '', label: '' },
        { value: '', label: '' },
    ]);
    const three = [{ value: 'a', label: 'A' }, { value: 'b', label: 'B' }, { value: 'c', label: 'C' }];
    assert.deepEqual(ensureTwoOptions(three), three);
});
