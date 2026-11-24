import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import {
    SESSION_TABS,
    firstIncompleteSectionId,
    journey,
    overallProgress,
    primaryAction,
    sectionProgress,
    splitSections,
    tabFromUrl,
    urlWithTab,
} from '../../resources/js/utils/feedbackSessionView.js';

const intro = (id, key) => ({ id, key, section_type: 'intro', questions: [] });
const sec = (id, qs) => ({ id, section_type: 'questions', questions: qs.map((q) => ({ id: q })) });
const sections = [intro(1, 'objetivo'), sec(2, [10, 11]), sec(3, [20, 21, 22]), sec(4, [30]), intro(5, 'encerramento')];

describe('detalhe do feedback: aba pela URL', () => {
    it('tem as três abas, nesta ordem', () => {
        assert.deepEqual(SESSION_TABS.map((t) => t.label), ['Respostas', 'Gravação e relatório', 'Assinaturas']);
    });

    it('lê ?aba= e cai em «Respostas» sem parâmetro ou com valor desconhecido', () => {
        assert.equal(tabFromUrl('/client/feedbacks/sessions/7?aba=gravacao'), 'gravacao');
        assert.equal(tabFromUrl('/admin/feedbacks/sessions/7?x=1&aba=assinaturas#topo'), 'assinaturas');
        assert.equal(tabFromUrl('/client/feedbacks/sessions/7'), 'respostas');
        assert.equal(tabFromUrl('/client/feedbacks/sessions/7?aba=hackeada'), 'respostas');
    });

    it('monta a URL da aba sem perder outros parâmetros; «Respostas» sai da URL', () => {
        assert.equal(urlWithTab('/client/feedbacks/sessions/7', 'gravacao'), '/client/feedbacks/sessions/7?aba=gravacao');
        assert.equal(urlWithTab('/s/7?x=1&aba=gravacao', 'assinaturas'), '/s/7?x=1&aba=assinaturas');
        assert.equal(urlWithTab('/s/7?aba=gravacao', 'respostas'), '/s/7');
    });
});

describe('detalhe do feedback: estado de cada seção', () => {
    it('Não iniciada, Em andamento X/N e Concluída (só perguntas do modelo)', () => {
        assert.deepEqual(sectionProgress(sec(2, [10, 11]), {}), { answered: 0, total: 2, status: 'not_started', label: 'Não iniciada' });
        assert.equal(sectionProgress(sec(3, [20, 21, 22]), { 20: 'sim', 21: '' }).label, 'Em andamento 1/3');
        assert.equal(sectionProgress(sec(2, [10, 11]), { 10: 'a', 11: ['x'] }).status, 'done');
        assert.equal(sectionProgress(sec(2, [10, 11]), { 10: 'a', 11: ['x'] }).label, 'Concluída');
    });

    it('conta respostas compostas com algum valor e ignora vazias', () => {
        assert.equal(sectionProgress(sec(9, [1, 2, 3]), { 1: { start: '', stop: 'x' }, 2: [], 3: { start: '' } }).answered, 1);
    });

    it('separa «Sobre este modelo» (Objetivo), seções respondíveis e o Encerramento', () => {
        const { about, body, closing } = splitSections(sections);
        assert.deepEqual(about.map((s) => s.id), [1]);
        assert.deepEqual(body.map((s) => s.id), [2, 3, 4]);
        assert.deepEqual(closing.map((s) => s.id), [5]);
    });

    it('progresso geral e primeira seção incompleta', () => {
        const answers = { 10: 'a', 11: 'b', 20: 'c' };
        assert.deepEqual(overallProgress(sections, answers), { answered: 3, total: 6 });
        assert.equal(firstIncompleteSectionId(sections, answers), 3);
        assert.equal(firstIncompleteSectionId(sections, {}), 2);
        assert.equal(firstIncompleteSectionId(sections, { 10: 1, 11: 1, 20: 1, 21: 1, 22: 1, 30: 1 }), null);
    });
});

describe('detalhe do feedback: ação principal por estado', () => {
    const open = { status: 'in_progress', signatures: [] };

    it('em preenchimento com perguntas sem resposta: «Continuar preenchimento»', () => {
        assert.equal(primaryAction(open, { answered: 0, total: 42 }).key, 'continue');
        assert.equal(primaryAction(open, { answered: 41, total: 42 }).label, 'Continuar preenchimento');
    });

    it('tudo respondido (ou modelo sem perguntas): «Enviar para assinatura»', () => {
        assert.equal(primaryAction(open, { answered: 42, total: 42 }).key, 'send');
        assert.equal(primaryAction(open, { answered: 0, total: 0 }).label, 'Enviar para assinatura');
    });

    it('em assinatura ou concluído: «Ver assinaturas»; cancelado: nenhuma', () => {
        assert.equal(primaryAction({ status: 'awaiting_signatures', signatures: [{}] }, { answered: 1, total: 42 }).key, 'signatures');
        assert.equal(primaryAction({ status: 'in_progress', signatures: [{}] }, { answered: 1, total: 42 }).label, 'Ver assinaturas');
        assert.equal(primaryAction({ status: 'completed', signatures: [{}, {}] }, { answered: 42, total: 42 }).label, 'Ver assinaturas');
        assert.equal(primaryAction({ status: 'cancelled' }, { answered: 0, total: 4 }), null);
    });
});

describe('detalhe do feedback: jornada', () => {
    it('Preenchimento X/Y › Assinaturas › Concluído, com a versão compacta', () => {
        const j = journey({ status: 'in_progress', signatures: [] }, { answered: 12, total: 42 });
        assert.deepEqual(j.steps.map((s) => [s.label, s.status]), [['Preenchimento', 'current'], ['Assinaturas', 'todo'], ['Concluído', 'todo']]);
        assert.equal(j.steps[0].detail, '12/42');
        assert.equal(j.compact, 'Preenchimento 12/42 · 1 de 3');
    });

    it('em assinatura mostra quantas foram assinadas; concluído marca tudo', () => {
        const j = journey({ status: 'awaiting_signatures', signatures: [{ signed_at: 'x' }, { signed_at: null }] }, { answered: 42, total: 42 });
        assert.deepEqual(j.steps.map((s) => s.status), ['done', 'current', 'todo']);
        assert.equal(j.compact, 'Assinaturas 1 de 2 assinadas · 2 de 3');
        assert.deepEqual(journey({ status: 'completed', signatures: [] }, { answered: 1, total: 1 }).steps.map((s) => s.status), ['done', 'done', 'done']);
    });
});
