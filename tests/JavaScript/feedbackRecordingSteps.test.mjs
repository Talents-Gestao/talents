import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { RECORDING_STEP_LABELS, recordingSteps } from '../../resources/js/utils/feedbackRecordingSteps.js';

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
