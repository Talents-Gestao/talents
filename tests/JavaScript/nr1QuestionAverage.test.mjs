import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

/** Espelha a média de risco do detalhamento por pergunta (Nr1SurveyResultsPanel). */
function questionAverage(question) {
    if (!question?.total) {
        return null;
    }
    const reverse = Boolean(question.reverse_score);
    let weighted = 0;
    for (const value of [1, 2, 3, 4, 5]) {
        const effective = reverse ? 6 - value : value;
        weighted += effective * (question.counts?.[value] ?? 0);
    }
    return weighted / question.total;
}

describe('questionAverage com polaridade', () => {
    it('enunciado desfavorável: Sempre (5) eleva o índice de risco', () => {
        const avg = questionAverage({
            reverse_score: false,
            total: 10,
            counts: { 1: 0, 2: 0, 3: 0, 4: 0, 5: 10 },
        });
        assert.equal(avg, 5);
    });

    it('enunciado favorável: Sempre (5) vira risco baixo (1)', () => {
        const avg = questionAverage({
            reverse_score: true,
            total: 50,
            counts: { 1: 0, 2: 1, 3: 3, 4: 16, 5: 30 },
        });
        // (6-2)*1 + (6-3)*3 + (6-4)*16 + (6-5)*30 = 4+9+32+30 = 75 → 75/50 = 1,5
        assert.equal(avg, 1.5);
    });

    it('sem respostas devolve null', () => {
        assert.equal(questionAverage({ reverse_score: true, total: 0, counts: {} }), null);
    });
});
