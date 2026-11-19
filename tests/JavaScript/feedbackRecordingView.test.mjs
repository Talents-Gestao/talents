import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';

import {
    RECORDING_STATES,
    canAdjustSpeakers,
    canRegenerateReport,
    clock,
    filterRows,
    highlightParts,
    parseTranscript,
    plainName,
    recordingState,
    speakersLine,
} from '../../resources/js/utils/feedbackRecordingView.js';

const root = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const fixture = (name) => readFileSync(join(root, 'tests', 'Fixtures', 'plaud', name), 'utf8');

const recording = { id: 1, has_speaker_labels: true };
const admin = { canProcess: true, canViewOriginal: true, locked: false };
const speakers = [
    { label: 'Speaker 1', role: null },
    { label: 'Speaker 2', role: null },
];
const mapped = [
    { label: 'Speaker 1', role: 'leader' },
    { label: 'Speaker 2', role: 'employee' },
];
const ai = (over = {}) => ({
    status: 'uploaded',
    gate: { allowed: true },
    limit_reached: false,
    speakers,
    speakers_mapped: false,
    clean: null,
    report: null,
    ...over,
});
const people = { leader: 'Rafael Mendes (fictício)', employee: 'Bruno Siqueira (fictício)' };

describe('Gravação e relatório: um estado por vez', () => {
    it('Sem gravação', () => {
        assert.equal(recordingState(null, null, admin), 'empty');
        assert.equal(RECORDING_STATES.empty.label, 'Sem gravação');
    });

    it('Confirmar falantes (com ou sem a IA liberada)', () => {
        assert.equal(recordingState(recording, ai(), admin), 'speakers');
        assert.equal(recordingState(recording, ai({ gate: { allowed: false } }), admin), 'speakers');
        assert.equal(RECORDING_STATES.speakers.label, 'Confirmar falantes');
        // Quem não pode processar não vê a etapa: só a espera.
        assert.equal(recordingState(recording, ai({ gate: { allowed: false } }), { canProcess: false }), 'waiting');
    });

    it('Falantes confirmados: resumo numa linha, sem parênteses duplos, e «Alterar» antes de processar', () => {
        const confirmed = ai({ speakers: mapped, speakers_mapped: true });
        assert.equal(recordingState(recording, confirmed, admin), 'queued');
        assert.equal(speakersLine(mapped, people), 'Falante 1 = Rafael Mendes (líder) · Falante 2 = Bruno Siqueira (colaborador)');
        assert.equal(speakersLine([...mapped, { label: 'Speaker 3', role: 'other' }], {}), 'Falante 1 = líder · Falante 2 = colaborador · Falante 3 = outra pessoa');
        assert.equal(canAdjustSpeakers(confirmed, admin), true);
        assert.equal(canAdjustSpeakers({ ...confirmed, status: 'processing' }, admin), false);
        assert.equal(canAdjustSpeakers({ ...confirmed, clean: { text: 'x' } }, admin), false);
        assert.equal(canAdjustSpeakers(confirmed, { ...admin, locked: true }), false);
    });

    it('Aguardando liberação da IA (gate fechado) e limite mensal', () => {
        assert.equal(recordingState(recording, ai({ speakers: mapped, speakers_mapped: true, gate: { allowed: false } }), admin), 'waiting');
        assert.equal(recordingState(recording, ai({ speakers: [], gate: { allowed: false } }), admin), 'waiting');
        assert.equal(RECORDING_STATES.waiting.label, 'Aguardando liberação da IA');
        assert.equal(recordingState(recording, ai({ speakers: mapped, speakers_mapped: true, limit_reached: true }), admin), 'limit');
    });

    it('Processando', () => {
        assert.equal(recordingState(recording, ai({ status: 'processing', speakers: mapped, speakers_mapped: true }), admin), 'processing');
        assert.equal(RECORDING_STATES.processing.label, 'Processando');
    });

    it('Relatório pronto, com «Gerar relatório novamente» só quando aplicável', () => {
        const ready = ai({ status: 'ready', speakers: mapped, speakers_mapped: true, clean: { text: 'x' }, report: {} });
        assert.equal(recordingState(recording, ready, admin), 'ready');
        assert.equal(RECORDING_STATES.ready.label, 'Relatório pronto');
        assert.equal(canRegenerateReport(ready, admin), true);
        assert.equal(canRegenerateReport({ ...ready, gate: { allowed: false } }, admin), false);
        assert.equal(canRegenerateReport(ready, { ...admin, locked: true }), false);
    });

    it('Erro', () => {
        assert.equal(recordingState(recording, ai({ status: 'failed', error: 'Falha' }), admin), 'failed');
        assert.equal(RECORDING_STATES.failed.label, 'Erro');
    });

    it('Gravação travada (concluído ou cancelado) e sem permissão: só a transcrição guardada', () => {
        assert.equal(recordingState(recording, ai(), { ...admin, locked: true }), 'idle');
        assert.equal(recordingState(recording, ai({ speakers: [] }), { canProcess: false }), 'idle');
    });
});

describe('Transcrição como conversa', () => {
    it('lê os formatos do Plaud com tempo (mm:ss) e o nome mapeado', () => {
        const cases = { 'plaud-falantes-tempo.txt': 4, 'plaud-falantes-tempo-rotulo-antes.txt': 3 };
        for (const [file, n] of Object.entries(cases)) {
            const rows = parseTranscript(fixture(file), { speakers: mapped, people });
            assert.deepEqual(rows.map((r) => r.time), ['00:01', '00:06', '00:12', '00:18'].slice(0, n), file);
            assert.deepEqual(rows.map((r) => r.who), ['Rafael Mendes', 'Bruno Siqueira', 'Rafael Mendes', 'Bruno Siqueira'].slice(0, n), file);
            assert.equal(rows[0].text, 'Bom dia, Larissa. Vamos começar o alinhamento do semestre?');
            assert.equal(rows[0].roleNoun, 'líder');
        }
    });

    it('sem mapa, mostra «Falante N»; sem tempo, a coluna fica vazia', () => {
        const rows = parseTranscript(fixture('plaud-falantes-sem-tempo.txt'));
        assert.deepEqual(rows.map((r) => r.who), ['Falante 1', 'Falante 2', 'Falante 1', 'Falante 2']);
        assert.ok(rows.every((r) => r.time === ''));
    });

    it('nomes próprios contam com duas falas; «Obs:» no meio do texto não vira falante', () => {
        const rows = parseTranscript(fixture('plaud-nomes-tempo.txt'));
        assert.deepEqual(rows.map((r) => r.who), ['Marcos', 'Larissa', 'Marcos', 'Larissa']);
        const plain = parseTranscript(fixture('plaud-sem-falantes-sem-tempo.txt'));
        assert.equal(plain.length, 3);
        assert.ok(plain.every((r) => r.who === ''));
        assert.match(plain[1].text, /Obs: a reunião de quinta/);
    });

    it('SRT convertido e texto limpo com papéis', () => {
        const srt = parseTranscript('[00:00:01] Speaker 1: Bom dia.\n[00:00:06] Bom dia! Tudo certo.', { speakers: mapped, people });
        assert.deepEqual(srt.map((r) => [r.time, r.who, r.text]), [['00:01', 'Rafael Mendes', 'Bom dia.'], ['00:06', '', 'Bom dia! Tudo certo.']]);

        const clean = parseTranscript('[00:00:01] Líder: Bom dia, Bruno.\n\n[01:02:03] Colaborador: Combinado.\n\nOutra pessoa 1: Oi.', { people });
        assert.deepEqual(clean.map((r) => [r.time, r.who, r.role]), [['00:01', 'Rafael Mendes', 'leader'], ['62:03', 'Bruno Siqueira', 'employee'], ['', 'Outra pessoa 1', 'other']]);
        assert.deepEqual(parseTranscript('Líder: Oi.', {}).map((r) => r.who), ['Líder']);
    });

    it('busca sem acento nem maiúsculas e marcação sem HTML', () => {
        const rows = parseTranscript(fixture('plaud-falantes-tempo.txt'), { speakers: mapped, people });
        assert.equal(filterRows(rows, 'COMECAR').length, 1);
        assert.equal(filterRows(rows, 'bruno').length, 2, 'busca também em quem fala');
        assert.equal(filterRows(rows, '  ').length, rows.length);
        assert.deepEqual(highlightParts('Vamos começar já', 'comecar'), [
            { text: 'Vamos ', match: false },
            { text: 'começar', match: true },
            { text: ' já', match: false },
        ]);
        assert.deepEqual(highlightParts('<b>x</b>', 'x'), [{ text: '<b>', match: false }, { text: 'x', match: true }, { text: '</b>', match: false }]);
    });

    it('relógio e nomes', () => {
        assert.equal(clock('00:00:01'), '00:01');
        assert.equal(clock('[01:02:03,500]'), '62:03');
        assert.equal(clock('12:34'), '12:34');
        assert.equal(clock('x'), '');
        assert.equal(plainName('Rafael Mendes (fictício)'), 'Rafael Mendes');
    });
});
