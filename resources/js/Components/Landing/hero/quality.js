/**
 * Níveis de qualidade da Hero em vidro (puro, testável).
 * alto: material com dispersão/refração em 3 amostras, sombras em camadas, DPR até 2.
 * médio: refração em 1 amostra, sombra simples, DPR até 1,5.
 * baixo: vidro em CSS com a mesma composição, sem WebGL.
 */
export const TIERS = ['low', 'medium', 'high'];

export const detectTier = ({
    webgl = true,
    webgl2 = true,
    deviceMemory = 8,
    cores = 8,
    saveData = false,
    reducedMotion = false,
    coarsePointer = false,
} = {}) => {
    if (reducedMotion || !webgl || saveData || deviceMemory <= 2 || cores <= 2) return 'low';
    if (webgl2 && deviceMemory >= 8 && cores >= 8 && !coarsePointer) return 'high';
    return 'medium';
};

export const TIER_CONFIG = {
    high: { maxDpr: 2, shadowLayers: 3, dispersion: true, budgetMs: 20 },
    medium: { maxDpr: 1.5, shadowLayers: 1, dispersion: false, budgetMs: 26 },
};

/** Percentil de uma lista de tempos (ms). */
export const percentile = (arr, q) => {
    if (!arr.length) return 0;
    const s = [...arr].sort((a, b) => a - b);
    const i = Math.min(s.length - 1, Math.max(0, Math.ceil(q * s.length) - 1));
    return s[i];
};

/**
 * Decide o ajuste após uma janela de medição: reduz o DPR em passos de 0,25 enquanto o p95 passar do
 * orçamento; no DPR 1 e ainda lento, rebaixa de alto para médio; médio lento no DPR 1 → baixo (CSS).
 */
export const adaptQuality = ({ tier, dpr, p95 }) => {
    const budget = TIER_CONFIG[tier]?.budgetMs ?? 26;
    if (p95 <= budget) return { tier, dpr, changed: false };
    if (dpr > 1) return { tier, dpr: Math.max(1, Math.round((dpr - 0.25) * 100) / 100), changed: true };
    if (tier === 'high') return { tier: 'medium', dpr: 1, changed: true };
    return { tier: 'low', dpr: 1, changed: true };
};
