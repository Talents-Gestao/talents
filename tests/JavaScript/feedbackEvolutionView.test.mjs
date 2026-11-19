import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { emptyStateText, shouldPoll, sourcesText, TONE_STYLES, toneStyle } from '../../resources/js/utils/feedbackEvolutionView.js';

describe('Evolução do colaborador (apresentação)', () => {
    it('cada tom tem rótulo em texto e estilo; tom desconhecido vira Neutro', () => {
        for (const [key, style] of Object.entries(TONE_STYLES)) {
            assert.ok(style.label.length > 0, key);
            assert.match(style.classes, /ring-/);
        }
        assert.equal(toneStyle('atencao').label, 'Atenção');
        assert.equal(toneStyle('xyz').label, 'Neutro');
    });

    it('estado vazio explica quantos relatórios faltam', () => {
        assert.equal(
            emptyStateText({ min_sessions: 2, eligible_count: 1, sessions_count: 3 }),
            'A evolução aparece quando houver pelo menos 2 feedbacks com relatório pronto. Hoje há 1 de 3 feedbacks com relatório; falta 1.',
        );
        assert.match(emptyStateText({ min_sessions: 2, eligible_count: 0, sessions_count: 1 }), /1 feedback com relatório; faltam 2\./);
        assert.match(emptyStateText({ min_sessions: 2, eligible_count: 0, sessions_count: 0 }), /ainda não tem feedbacks visíveis/);
    });

    it('fontes viram texto legível e só a narrativa em geração atualiza a tela', () => {
        assert.equal(sourcesText([{ label: 'S1 · mar/2026' }, { label: 'S3 · nov/2026' }]), 'S1 · mar/2026, S3 · nov/2026');
        assert.equal(shouldPoll({ status: 'processing' }), true);
        assert.equal(shouldPoll({ status: 'ready' }), false);
        assert.equal(shouldPoll(null), false);
    });
});
