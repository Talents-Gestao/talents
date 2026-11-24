import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { readFileSync } from 'node:fs';

import {
    RECORDING_STEP_LABELS,
    REWIND_PROCESSING_REASON,
    recordingSteps,
    rewindMessage,
    rewindOptions,
} from '../../resources/js/utils/feedbackRecordingSteps.js';

const status = (recording, ai) => recordingSteps(recording, ai).map((s) => s.status);
const speakers = { has_speaker_labels: true };
const noSpeakers = { has_speaker_labels: false };
const ai = (over = {}) => ({ status: 'uploaded', speakers: [{ label: 'Speaker 1' }], speakers_mapped: false, clean: null, ...over });

describe('etapas da seção «Gravação e relatório»', () => {
    it('tem as quatro etapas, nesta ordem', () => {
        assert.deepEqual(RECORDING_STEP_LABELS, ['Enviar', 'Confirmar falantes', 'Processar', 'Revisar relatório']);
        assert.deepEqual(recordingSteps(null, null).map((s) => s.label), RECORDING_STEP_LABELS);
    });

    it('sem gravação, a etapa atual é enviar', () => {
        assert.deepEqual(status(null, null), ['current', 'todo', 'todo', 'todo']);
    });

    it('enviado: confirmar falantes (ou pular, sem falantes) e depois processar', () => {
        assert.deepEqual(status(speakers, ai()), ['done', 'current', 'todo', 'todo']);
        assert.deepEqual(status(speakers, ai({ speakers_mapped: true })), ['done', 'done', 'current', 'todo']);
        assert.deepEqual(status(noSpeakers, ai({ speakers: [] })), ['done', 'skipped', 'current', 'todo']);
    });

    it('processando, falhou e pronto', () => {
        assert.deepEqual(status(speakers, ai({ status: 'processing', speakers_mapped: true })), ['done', 'done', 'current', 'todo']);
        assert.deepEqual(status(speakers, ai({ status: 'failed', speakers_mapped: true })), ['done', 'done', 'error', 'todo']);
        assert.deepEqual(status(speakers, ai({ status: 'ready', speakers_mapped: true, clean: {} })), ['done', 'done', 'done', 'current']);
        assert.deepEqual(status(noSpeakers, ai({ status: 'ready', speakers: [], clean: {} })), ['done', 'skipped', 'done', 'current']);
    });

    it('sem o estado da IA (só a gravação), não avança além dos falantes', () => {
        assert.deepEqual(status(speakers, null), ['done', 'current', 'todo', 'todo']);
        assert.deepEqual(status(noSpeakers, null), ['done', 'skipped', 'todo', 'todo']);
    });
});

describe('voltar etapas na barra «Gravação»', () => {
    const can = { canProcess: true, canDelete: true, locked: false, rewindBlockedReason: null };
    const gate = { allowed: true };
    const ready = ai({ status: 'ready', speakers_mapped: true, clean: {}, report: {}, gate });
    const clickable = (recording, state, access = can) =>
        rewindOptions(recordingSteps(recording, state), recording, state, access).map((o) => o.clickable);
    const reasons = (recording, state, access = can) =>
        rewindOptions(recordingSteps(recording, state), recording, state, access).map((o) => o.reason);

    it('só etapas concluídas são clicáveis; a atual e as futuras, nunca', () => {
        assert.deepEqual(clickable(null, null), [false, false, false, false]);
        assert.deepEqual(clickable(speakers, ai({ gate })), [true, false, false, false]);
        assert.deepEqual(clickable(speakers, ai({ speakers_mapped: true, gate })), [true, true, false, false]);
        assert.deepEqual(clickable(speakers, ready), [true, true, true, false]);
        assert.deepEqual(clickable(speakers, ai({ status: 'failed', speakers_mapped: true, clean: {}, gate })), [true, true, false, false]);
    });

    it('sem falantes no arquivo, «Confirmar falantes» (não se aplica) não é clicável', () => {
        assert.deepEqual(clickable(noSpeakers, ai({ status: 'ready', speakers: [], clean: {}, gate })), [true, false, true, false]);
    });

    it('durante o processamento nada é clicável e o motivo aparece', () => {
        const processing = ai({ status: 'processing', speakers_mapped: true, gate });
        assert.deepEqual(clickable(speakers, processing), [false, false, false, false]);
        assert.deepEqual(reasons(speakers, processing), [REWIND_PROCESSING_REASON, REWIND_PROCESSING_REASON, '', '']);
    });

    it('com assinatura solicitada ou concluída, nada é clicável e o motivo vem do servidor', () => {
        const reason = 'O feedback está em assinatura ou concluído: as etapas da gravação não podem ser refeitas.';
        const access = { ...can, rewindBlockedReason: reason };
        assert.deepEqual(clickable(speakers, ready, access), [false, false, false, false]);
        assert.deepEqual(reasons(speakers, ready, access), [reason, reason, reason, '']);
    });

    it('IA bloqueada ou limite atingido: «Processar» não é clicável e explica o motivo', () => {
        const locked = { ...ready, gate: { allowed: false } };
        assert.deepEqual(clickable(speakers, locked), [true, true, false, false]);
        assert.match(reasons(speakers, locked)[2], /IA ainda não foi liberada/);
        const limit = { ...ready, limit_reached: true };
        assert.deepEqual(clickable(speakers, limit), [true, true, false, false]);
        assert.match(reasons(speakers, limit)[2], /limite mensal/);
    });

    it('sem permissão para alterar a gravação, a barra fica só informativa (sem motivo)', () => {
        const none = { canProcess: false, canDelete: false, locked: false, rewindBlockedReason: null };
        assert.deepEqual(clickable(speakers, ready, none), [false, false, false, false]);
        assert.deepEqual(reasons(speakers, ready, none), ['', '', '', '']);
        // Só «excluir»: volta para «Enviar»; só «processar»: falantes e processar.
        assert.deepEqual(clickable(speakers, ready, { ...none, canDelete: true }), [true, false, false, false]);
        assert.deepEqual(clickable(speakers, ready, { ...none, canProcess: true }), [false, true, true, false]);
        assert.deepEqual(clickable(speakers, ready, { ...can, locked: true }), [false, false, false, false]);
    });

    it('mensagens da confirmação', () => {
        assert.match(rewindMessage('upload', ready), /serão excluídos\. Esta ação não pode ser desfeita\./);
        assert.match(rewindMessage('speakers', ready), /O relatório atual vai para o histórico e será preciso processar de novo\./);
        assert.doesNotMatch(rewindMessage('speakers', ai({ speakers_mapped: true })), /histórico/);
        assert.match(rewindMessage('process', ready), /versão anterior/);
    });

    it('a barra usa botão com «Voltar para …», sem confirmação nativa do navegador', () => {
        const bar = readFileSync(new URL('../../resources/js/Components/Feedback/FeedbackRecordingSteps.vue', import.meta.url), 'utf8');
        const panel = readFileSync(new URL('../../resources/js/Components/Feedback/FeedbackRecordingPanel.vue', import.meta.url), 'utf8');
        assert.match(bar, /`Voltar para \$\{step\.label\}`/);
        assert.match(bar, /role="tooltip"/);
        assert.match(panel, /FeedbackRecordingRewindModal/);
        assert.doesNotMatch(panel + bar, /window\.confirm|confirm\(['"`]/);
    });
});
