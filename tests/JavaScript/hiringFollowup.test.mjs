import test from 'node:test';
import assert from 'node:assert/strict';
import {
    canSendAny,
    copyText,
    followupStatusClass,
    invitationStateClass,
    plannedAudiences,
    sendOutcomeLabel,
} from '../../resources/js/utils/hiringFollowup.js';

const eligibility = {
    email: { collaborator: null, manager: 'Gestor não definido.' },
    copy: { collaborator: 'Colaborador sem autorização de e-mail.', manager: 'Gestor não definido.' },
};

test('plannedAudiences mostra o motivo de cada destino', () => {
    assert.deepEqual(plannedAudiences(eligibility, 'both', 'email'), [
        { audience: 'collaborator', reason: null },
        { audience: 'manager', reason: 'Gestor não definido.' },
    ]);
    assert.deepEqual(plannedAudiences(eligibility, 'manager', 'email'), [{ audience: 'manager', reason: 'Gestor não definido.' }]);
});

test('envio parcial: «Ambos» segue se um destino puder receber', () => {
    assert.equal(canSendAny(eligibility, 'both', 'email'), true);
    assert.equal(canSendAny(eligibility, 'manager', 'email'), false);
    assert.equal(canSendAny(eligibility, 'both', 'copy'), false);
});

test('rótulos do resultado nunca falam em entregue', () => {
    assert.equal(sendOutcomeLabel({ outcome: 'emailed' }), 'Enviado ao servidor de e-mail');
    assert.equal(sendOutcomeLabel({ outcome: 'skipped' }), 'Não enviado');
    assert.equal(sendOutcomeLabel({ outcome: 'ready_to_copy' }), 'Pronto para copiar');
    assert.equal(copyText({ message: 'Olá', link: 'https://x' }), 'Olá');
    assert.equal(copyText({ link: 'https://x' }), 'https://x');
});

test('classes dos selos', () => {
    assert.match(followupStatusClass('overdue'), /red/);
    assert.match(invitationStateClass('copied'), /sky/);
    assert.match(invitationStateClass('emailed'), /emerald/);
});
