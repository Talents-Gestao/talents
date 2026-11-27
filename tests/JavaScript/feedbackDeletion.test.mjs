import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';

import {
    TEMPLATE_IN_USE_MESSAGE,
    answersLabel,
    canConfirmDeletion,
    feedbacksLabel,
    templateDeletionCopy,
} from '../../resources/js/utils/feedbackDeletion.js';

describe('alerta de exclusão de modelo', () => {
    it('modelo nunca usado: confirmação simples, sem caixa de confirmação', () => {
        const copy = templateDeletionCopy({ title: 'Trimestral', sessions_count: 0, blocked: false });
        assert.equal(copy.blocked, false);
        assert.equal(copy.requiresCheck, false);
        assert.equal(copy.confirmLabel, 'Excluir modelo');
        assert.match(copy.message, /«Trimestral»/);
    });

    it('modelo usado: não se exclui, a orientação é arquivar e nenhum feedback sai junto', () => {
        const copy = templateDeletionCopy({ title: 'Trimestral', sessions_count: 3, blocked: true, blocked_message: TEMPLATE_IN_USE_MESSAGE });
        assert.equal(copy.blocked, true);
        assert.equal(copy.confirmLabel, null);
        assert.equal(copy.message, 'Este modelo já foi usado em feedbacks e não pode ser excluído. Arquive-o para que não apareça em novos feedbacks.');
        assert.doesNotMatch(copy.message, /serão excluídos/);
    });

    it('sem a mensagem do servidor, usa o mesmo texto', () => {
        assert.equal(templateDeletionCopy({ title: 'X', sessions_count: 1 }).message, TEMPLATE_IN_USE_MESSAGE);
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

    it('«Excluir» só para modelo nunca usado; o alerta consulta o servidor', () => {
        const index = read('resources/js/Pages/Client/Feedbacks/Templates/Index.vue');
        assert.match(index, /v-if="permissions\.delete && template\.deletable"/);
        assert.match(index, /'templates\.archive'/);
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
