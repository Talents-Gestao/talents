import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { commitmentsView, keyQuotesView, ownerLabel, pointsView } from '../../resources/js/utils/feedbackReportView.js';

describe('leitura do relatório de IA do feedback', () => {
    it('pontos: o rótulo genérico repetido some e fica só a citação; texto próprio vem antes da citação', () => {
        const points = [
            { texto: 'Reconhecimento feito pelo líder durante a conversa.', trecho: 'Parabéns pelo projeto.' },
            { texto: 'Reconhecimento feito pelo líder durante a conversa.', trecho: 'Você tem feito um trabalho excelente.' },
            { texto: 'Preparo com dados do período.', trecho: 'Trouxe os números.' },
            { texto: 'Sem citação.', trecho: null },
            { texto: 'Trouxe os números!', trecho: 'trouxe os números' },
        ];

        assert.deepEqual(pointsView(points), [
            { texto: null, trecho: 'Parabéns pelo projeto.' },
            { texto: null, trecho: 'Você tem feito um trabalho excelente.' },
            { texto: 'Preparo com dados do período.', trecho: 'Trouxe os números.' },
            { texto: 'Sem citação.', trecho: null },
            { texto: null, trecho: 'trouxe os números' },
        ]);
    });

    it('compromissos: a citação só aparece quando difere da frase; prazo só quando existe; responsável pelo nome', () => {
        const people = { leader: 'Rafael Mendes', employee: 'Camila Duarte' };
        const view = commitmentsView(
            [
                { id: 'c1', descricao: 'Eu vou mandar a proposta até sexta-feira.', responsavel: 'colaborador', responsavel_label: 'Colaborador', prazo: 'até sexta-feira', trecho: 'Eu vou mandar a proposta até sexta-feira.' },
                { id: 'c2', descricao: 'Revisar o fluxo.', responsavel: 'lider', responsavel_label: 'Líder', prazo: null, trecho: 'Combinamos de revisar o fluxo.' },
                { id: 'c3', descricao: 'Alinhar toda segunda.', responsavel: 'ambos', responsavel_label: 'Líder e colaborador', prazo: '', trecho: null },
                { id: 'c4', descricao: 'Algo.', responsavel: 'nao_citado', responsavel_label: 'Responsável não citado', prazo: null, trecho: null },
            ],
            people,
        );

        assert.deepEqual(view.map((c) => [c.trecho, c.owner, c.prazo]), [
            [null, 'Camila Duarte', 'até sexta-feira'],
            ['Combinamos de revisar o fluxo.', 'Rafael Mendes', null],
            [null, 'Rafael Mendes e Camila Duarte', null],
            [null, 'Responsável não citado', null],
        ]);
        assert.equal(ownerLabel({ responsavel: 'colaborador', responsavel_label: 'Colaborador' }, {}), 'Colaborador');
    });

    it('trechos-chave não repetem citações dos pontos e compromissos; sem sobra, a lista fica vazia', () => {
        const report = {
            pontos_fortes: [{ texto: 'x', trecho: 'Parabéns pelo projeto.' }],
            pontos_atencao: [{ texto: 'y', trecho: 'Chegou atrasado.' }],
            compromissos: [{ descricao: 'Eu vou mandar a proposta.', trecho: 'Eu vou mandar a proposta.' }],
            trechos_chave: [
                { texto: 'parabéns pelo projeto', falante_label: 'Líder' },
                { texto: 'Eu vou mandar a proposta.', falante_label: 'Colaborador' },
                { texto: 'Uma frase nova.', falante_label: 'Líder' },
                { texto: 'Uma frase nova!', falante_label: 'Líder' },
            ],
        };

        assert.deepEqual(keyQuotesView(report).map((t) => t.texto), ['Uma frase nova.']);
        assert.deepEqual(keyQuotesView({ ...report, trechos_chave: report.trechos_chave.slice(0, 2) }), []);
    });
});
