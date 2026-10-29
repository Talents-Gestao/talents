import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { checkNoAbruptSwap, meanAbsDiff } from './support/frameDiff.mjs';

const frame = (rgb, n = 16) => {
    const a = new Uint8ClampedArray(n * 4);
    for (let i = 0; i < n; i++) a.set([...rgb, 255], i * 4);
    return a;
};

describe('comparação de quadros da Hero', () => {
    it('quadros iguais não diferem e opostos diferem por 1', () => {
        assert.equal(meanAbsDiff(frame([10, 20, 30]), frame([10, 20, 30])), 0);
        assert.equal(meanAbsDiff(frame([0, 0, 0]), frame([255, 255, 255])), 1);
    });

    it('aceita uma entrada suave (fade) e recusa a troca brusca de Hero', () => {
        const smooth = [0, 1, 2, 3].map((i) => ({ t: i * 100, data: frame([240 - i * 4, 238 - i * 4, 245 - i * 2]) }));
        assert.ok(checkNoAbruptSwap(smooth, { maxTotal: 0.1, maxStep: 0.05 }).ok);

        // Hero antiga (clara) → nova (escura) de um quadro para o outro.
        const swap = [
            { t: 0, data: frame([245, 245, 247]) },
            { t: 100, data: frame([245, 245, 247]) },
            { t: 200, data: frame([90, 40, 130]) },
            { t: 300, data: frame([90, 40, 130]) },
        ];
        const result = checkNoAbruptSwap(swap, { maxTotal: 0.1, maxStep: 0.05 });
        assert.equal(result.ok, false);
        assert.ok(result.problems.some((p) => p.includes('salto')));
    });

    it('exige mais de um quadro', () => {
        assert.equal(checkNoAbruptSwap([{ t: 0, data: frame([1, 1, 1]) }], { maxTotal: 1, maxStep: 1 }).ok, false);
    });
});
