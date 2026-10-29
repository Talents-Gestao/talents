/**
 * Comparação de quadros da Hero (usada pelo teste de navegador `tests/JavaScript/browser/heroFrames.mjs`).
 * Os quadros são RGBA (Uint8ClampedArray) do mesmo tamanho, já reduzidos.
 */

/** Diferença média absoluta por canal (RGB), de 0 (iguais) a 1 (opostos). */
export const meanAbsDiff = (a, b) => {
    if (a.length !== b.length) throw new Error('Quadros de tamanhos diferentes');
    let sum = 0;
    let n = 0;
    for (let i = 0; i < a.length; i += 4) {
        sum += Math.abs(a[i] - b[i]) + Math.abs(a[i + 1] - b[i + 1]) + Math.abs(a[i + 2] - b[i + 2]);
        n += 3;
    }
    return n === 0 ? 0 : sum / n / 255;
};

/**
 * Falha se a Hero trocar de aparência de forma brusca.
 * `frames`: [{ t, data }] em ordem de tempo (t em ms), sem o quadro em branco inicial.
 *  - first→last: o quadro estático (1º) e o final (último) ficam dentro de `maxTotal`;
 *  - passo a passo: nenhum quadro difere do anterior mais que `maxStep` (a troca da Hero antiga pela nova é um degrau).
 */
export const checkNoAbruptSwap = (frames, { maxTotal, maxStep }) => {
    const problems = [];
    if (frames.length < 2) return { ok: false, problems: ['quadros insuficientes'], total: null, worstStep: null };
    const total = meanAbsDiff(frames[0].data, frames[frames.length - 1].data);
    if (total > maxTotal) problems.push(`quadro estático × final: ${total.toFixed(4)} > ${maxTotal}`);
    let worstStep = 0;
    for (let i = 1; i < frames.length; i++) {
        const d = meanAbsDiff(frames[i - 1].data, frames[i].data);
        worstStep = Math.max(worstStep, d);
        if (d > maxStep) problems.push(`salto de ${d.toFixed(4)} entre ${Math.round(frames[i - 1].t)} ms e ${Math.round(frames[i].t)} ms (máx. ${maxStep})`);
    }
    return { ok: problems.length === 0, problems, total, worstStep };
};
