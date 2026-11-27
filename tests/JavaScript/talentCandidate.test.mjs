import test from 'node:test';
import assert from 'node:assert/strict';
import {
    candidateAuditDescription,
    contactPreferenceLabel,
    formatFileSize,
} from '../../resources/js/utils/talentCandidate.js';

test('contactPreferenceLabel usa o rótulo da opção ou «Não informada»', () => {
    const options = [{ value: 'whatsapp', label: 'WhatsApp' }];
    assert.equal(contactPreferenceLabel('whatsapp', options), 'WhatsApp');
    assert.equal(contactPreferenceLabel(null, options), 'Não informada');
    assert.equal(contactPreferenceLabel('outro', options), 'outro');
});

test('candidateAuditDescription lista só os nomes dos campos alterados', () => {
    assert.equal(
        candidateAuditDescription({ action: 'updated', fields: ['email', 'email_reviewed'] }),
        'Campos alterados: e-mail, e-mail conferido',
    );
    assert.equal(candidateAuditDescription({ action: 'attachment_downloaded', attachment_name: 'laudo.pdf' }), 'laudo.pdf');
    assert.equal(candidateAuditDescription({ action: 'attachment_downloaded', attachment_name: null }), '');
    assert.equal(candidateAuditDescription({ action: 'archived' }), '');
});

test('formatFileSize formata bytes, KB e MB', () => {
    assert.equal(formatFileSize(512), '512 B');
    assert.equal(formatFileSize(2048), '2 KB');
    assert.equal(formatFileSize(5 * 1024 * 1024), '5 MB');
});
