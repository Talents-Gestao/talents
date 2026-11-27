import test from 'node:test';
import assert from 'node:assert/strict';
import {
    communicationStatusClass,
    contactLosesReview,
    contactsForChannel,
    emptyContact,
    fitsDailyLimit,
    reviewBlockers,
} from '../../resources/js/utils/hiringCommunication.js';

test('contactsForChannel filtra pelo canal permitido', () => {
    const contacts = [
        { id: 1, channels: ['email'] },
        { id: 2, channels: ['email', 'whatsapp'] },
        { id: 3, channels: [] },
    ];
    assert.deepEqual(contactsForChannel(contacts, 'whatsapp').map((c) => c.id), [2]);
    assert.deepEqual(contactsForChannel(contacts, 'email').map((c) => c.id), [1, 2]);
});

test('reviewBlockers aponta texto já enviado e contato inelegível', () => {
    const rows = [
        { contact_id: 1, already_done: true, reasons: [] },
        { contact_id: 2, already_done: false, reasons: ['Contato desabilitado.'] },
        { contact_id: 3, already_done: false, reasons: [] },
    ];
    assert.deepEqual(reviewBlockers(rows, false).map((r) => r.contact_id), [1, 2]);
    assert.deepEqual(reviewBlockers(rows, true).map((r) => r.contact_id), [2]);
});

test('fitsDailyLimit respeita o que resta no dia', () => {
    assert.equal(fitsDailyLimit(3, 3), true);
    assert.equal(fitsDailyLimit(4, 3), false);
    assert.equal(fitsDailyLimit(1, 0), false);
});

test('contactLosesReview: e-mail ou telefone alterado desfaz a conferência', () => {
    const original = { email: 'Ana@Empresa.com', phone: '+55 11 90000-0000' };
    assert.equal(contactLosesReview(original, { email: 'ana@empresa.com', phone: '+55 11 90000-0000' }), false);
    assert.equal(contactLosesReview(original, { email: 'outra@empresa.com', phone: '+55 11 90000-0000' }), true);
    assert.equal(contactLosesReview(original, { email: 'ana@empresa.com', phone: '+55 11 91111-1111' }), true);
});

test('status nunca vira «entregue» e contato novo começa habilitado sem canais', () => {
    assert.match(communicationStatusClass('sent'), /emerald/);
    assert.match(communicationStatusClass('copied'), /sky/);
    const contact = emptyContact();
    assert.equal(contact.is_enabled, true);
    assert.equal(contact.channel_email, false);
    assert.deepEqual(contact.topics, []);
});
