import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';

import {
    answersLabel,
    canConfirmDeletion,
    feedbacksLabel,
    templateDeletionCopy,
    templateDeletionPayload,
} from '../../resources/js/utils/feedbackDeletion.js';

describe('alerta de exclusão de modelo', () => {
    it('modelo nunca usado: confirmação simples, sem caixa de confirmação', () => {
        const copy = templateDeletionCopy({ title: 'Trimestral', sessions_count: 0 });
        assert.equal(copy.inUse, false);
        assert.equal(copy.requiresCheck, false);
        assert.equal(copy.confirmLabel, 'Excluir modelo');
        assert.match(copy.message, /«Trimestral»/);
    });

    it('modelo usado: alerta com impacto, botão com a contagem e confirmação explícita', () => {
        const copy = templateDeletionCopy({ title: 'Trimestral', sessions_count: 3 });
        assert.equal(copy.inUse, true);
        assert.equal(copy.requiresCheck, true);
        assert.equal(copy.confirmLabel, 'Excluir modelo e 3 feedbacks');
        assert.equal(
            copy.message,
            'Este modelo já foi usado em 3 feedbacks. Ao excluí-lo, os 3 feedbacks feitos com ele e todas as respostas '
                + 'também serão excluídos, e isso afeta o painel de Feedbacks (termômetro e 9-box) e os históricos dos '
                + 'colaboradores. Essa ação não pode ser desfeita por aqui.',
        );
        assert.match(copy.checkLabel, /«Trimestral» e os 3 feedbacks feitos com ele serão excluídos/);
    });

    it('singular com 1 feedback', () => {
        const copy = templateDeletionCopy({ title: 'X', sessions_count: 1 });
        assert.equal(copy.confirmLabel, 'Excluir modelo e 1 feedback');
        assert.match(copy.message, /^Este modelo já foi usado em 1 feedback\. Ao excluí-lo, o feedback feito com ele/);
    });

    it('envia a contagem vista no alerta para o servidor conferir', () => {
        assert.deepEqual(templateDeletionPayload({ sessions_count: 0 }, false), { sessions_count: 0 });
        assert.deepEqual(templateDeletionPayload({ sessions_count: 4 }, true), { confirmed: true, sessions_count: 4 });
        assert.deepEqual(templateDeletionPayload({ sessions_count: 4 }, false), { confirmed: false, sessions_count: 4 });
    });
});

describe('confirmação', () => {
    it('exige a caixa marcada quando pedida e bloqueia durante carregamento/envio', () => {
        assert.equal(canConfirmDeletion({ requiresCheck: true, checked: false, busy: false }), false);
        assert.equal(canConfirmDeletion({ requiresCheck: true, checked: true, busy: false }), true);
        assert.equal(canConfirmDeletion({ requiresCheck: false, checked: false, busy: false }), true);
        assert.equal(canConfirmDeletion({ requiresCheck: false, checked: true, busy: true }), false);
    });

    it('rótulos no singular e plural', () => {
        assert.equal(feedbacksLabel(1), '1 feedback');
        assert.equal(feedbacksLabel(0), '0 feedbacks');
        assert.equal(answersLabel(1), '1 resposta');
        assert.equal(answersLabel(12), '12 respostas');
    });
});

describe('telas', () => {
    const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
    const read = (path) => readFileSync(join(root, path), 'utf8');

    it('«Excluir» aparece também para modelo usado e abre o alerta com impacto do servidor', () => {
        const index = read('resources/js/Pages/Client/Feedbacks/Templates/Index.vue');
        assert.match(index, /v-if="permissions\.delete"\s/);
        assert.doesNotMatch(index, /!template\.sessions_count/);
        assert.match(index, /<FeedbackTemplateDeleteModal/);
        const modal = read('resources/js/Components/Feedback/FeedbackTemplateDeleteModal.vue');
        assert.match(modal, /feedbackRoute\('templates\.deletion'/);
    });

    it('excluir feedback: texto do alerta, dados de quem é e confirmação enviada', () => {
        const modal = read('resources/js/Components/Feedback/FeedbackSessionDeleteModal.vue');
        assert.match(modal, /Este feedback e todas as respostas serão excluídos e não poderão ser recuperados por aqui\./);
        for (const field of ['session.employee', 'session.leader', 'session.scheduled_at', 'session.answers_count']) {
            assert.ok(modal.includes(field), field);
        }
        assert.match(modal, /data: \{ confirmed: true \}/);
    });
});
