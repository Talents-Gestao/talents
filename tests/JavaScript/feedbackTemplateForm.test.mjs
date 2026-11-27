import assert from 'node:assert/strict';
import test from 'node:test';

import {
    ensureTwoOptions,
    normalizeTemplateOptions,
    templateFormPayload,
    templateVersionNotice,
    versionUsageLabel,
} from '../../resources/js/utils/feedbackTemplateForm.js';

const data = {
    title: 'Alinhamento',
    description: 'Uso interno',
    is_active: true,
    sections: [{ title: 'Resultados', description: '', questions: [{ body: 'O que foi entregue?' }] }],
};

test('o formulário envia nome, descrição e a estrutura completa (sem o antigo «Ativo»)', () => {
    assert.deepEqual(templateFormPayload(data), { title: 'Alinhamento', description: 'Uso interno', sections: data.sections });
});

test('aviso de versão: só quando a versão atual já foi usada, com o número da próxima', () => {
    assert.equal(templateVersionNotice(null), null);
    assert.equal(templateVersionNotice({ number: 1, used: false, sessions_count: 0, next_number: null }), null);

    const one = templateVersionNotice({ number: 1, used: true, sessions_count: 1, next_number: 2 });
    assert.match(one, /^Usado em 1 feedback; salvar mudanças nas seções e perguntas criará a versão 2\./);
    assert.match(one, /continuam na versão 1/);
    assert.match(one, /Mudar só o nome ou a descrição não cria versão/);

    assert.match(templateVersionNotice({ number: 3, used: true, sessions_count: 22, next_number: 4 }), /^Usado em 22 feedbacks; .*versão 4/);
});

test('histórico de versões: número, atual e uso', () => {
    assert.equal(versionUsageLabel({ number: 2, sessions_count: 0, is_current: true }), 'Versão 2 (atual) · sem feedbacks');
    assert.equal(versionUsageLabel({ number: 1, sessions_count: 1 }), 'Versão 1 · 1 feedback');
    assert.equal(versionUsageLabel({ number: 1, sessions_count: 5 }), 'Versão 1 · 5 feedbacks');
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
