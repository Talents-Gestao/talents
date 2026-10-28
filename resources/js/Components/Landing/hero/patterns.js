/**
 * Padrões de corte das direções da Hero (dados puros, testáveis com node --test).
 * Coordenadas normalizadas do painel: x para a direita, y para baixo, 0..1. A célula 0 é sempre a peça
 * do texto; a fronteira dela (`boundary`, de cima para baixo) é empurrada para a direita até conter todo o
 * texto e os CTAs com folga (keepout). No retrato o padrão é transposto (texto em cima).
 */

const smoothstep = (a, b, v) => {
    const t = Math.min(1, Math.max(0, (v - a) / (b - a)));
    return t * t * (3 - 2 * t);
};

const P = (x, y, extra = {}) => ({ p: [x, y], ...extra });
const CORNERS = {
    TL: P(0, 0, { fixed: true }),
    TR: P(1, 0, { fixed: true }),
    BR: P(1, 1, { fixed: true }),
    BL: P(0, 1, { fixed: true }),
};

/**
 * A — Metamorfose. Facetas desenhadas à mão a partir dos nós do logo (borboleta em rede, espelhada):
 * o corpo (Bd) fica na fronteira com o texto; a asa superior é uma faceta grande e calma (rosto e placa
 * inteiros), uma faixa estreita faz o tórax e a asa inferior, menor, se abre em quatro facetas.
 */
const METAMORFOSE = {
    points: {
        ...CORNERS,
        tTop: P(0.5, 0, { edge: 'x' }),
        Bd: P(0.56, 0.58),
        tBot: P(0.5, 1, { edge: 'x' }),
        U1: P(0.64, 0, { edge: 'x' }),
        R1: P(1, 0.46, { edge: 'y' }),
        R3: P(1, 0.7, { edge: 'y' }),
        D1: P(0.88, 1, { edge: 'x' }),
        D2: P(0.68, 1, { edge: 'x' }),
        V1: P(0.68, 0.44),
        L1: P(0.7, 0.84),
        L2: P(0.9, 0.86),
    },
    cells: [
        ['TL', 'tTop', 'Bd', 'tBot', 'BL'],
        ['tTop', 'U1', 'V1', 'Bd'],
        ['U1', 'TR', 'R1', 'V1'],
        ['Bd', 'V1', 'R1', 'R3'],
        ['Bd', 'R3', 'L2', 'L1'],
        ['R3', 'BR', 'D1', 'L2'],
        ['L1', 'L2', 'D1', 'D2'],
        ['Bd', 'L1', 'D2', 'tBot'],
    ],
    boundary: ['tTop', 'Bd', 'tBot'],
    hinge: 'Bd',
};

/** B — Profundidade: peça frontal do texto + quatro lâminas verticais (vidro canelado) sobre a foto. */
const PROFUNDIDADE = {
    points: {
        ...CORNERS,
        tTop: P(0.5, 0, { edge: 'x' }),
        tBot: P(0.47, 1, { edge: 'x' }),
        s1t: P(0.62, 0, { edge: 'x' }),
        s1b: P(0.6, 1, { edge: 'x' }),
        s2t: P(0.75, 0, { edge: 'x' }),
        s2b: P(0.74, 1, { edge: 'x' }),
        s3t: P(0.88, 0, { edge: 'x' }),
        s3b: P(0.88, 1, { edge: 'x' }),
    },
    cells: [
        ['TL', 'tTop', 'tBot', 'BL'],
        ['tTop', 's1t', 's1b', 'tBot'],
        ['s1t', 's2t', 's2b', 's1b'],
        ['s2t', 's3t', 's3b', 's2b'],
        ['s3t', 'TR', 'BR', 's3b'],
    ],
    boundary: ['tTop', 'tBot'],
};

/** C — Luz: três peças grandes e assimétricas; cortes com deriva lenta e mínima. */
const LUZ = {
    points: {
        ...CORNERS,
        tTop: P(0.55, 0, { edge: 'x', drift: [0.008, 17, 0.3] }),
        Kb: P(0.49, 0.8, { driftY: [0.02, 13, 1.7] }),
        tBot: P(0.46, 1, { edge: 'x' }),
        Rk: P(1, 0.84, { edge: 'y', driftY: [0.025, 11, 4.1] }),
    },
    cells: [
        ['TL', 'tTop', 'Kb', 'tBot', 'BL'],
        ['tTop', 'TR', 'Rk', 'Kb'],
        ['Kb', 'Rk', 'BR', 'tBot'],
    ],
    boundary: ['tTop', 'Kb', 'tBot'],
};

export const PATTERNS = { a: METAMORFOSE, b: PROFUNDIDADE, c: LUZ };

/** Retângulo da foto por direção (px do painel). B: a foto fica atrás e começa sob a peça do texto. */
export const imageRect = (direction, layout, g) => {
    if (direction !== 'b' || g.portrait) return layout.image;
    return { x: g.pw * 0.38, y: 0, w: g.pw * 0.62, h: g.ph };
};

/** x da fronteira (polilinha de cima para baixo) na altura y. */
export const boundaryX = (pts, names, y) => {
    for (let i = 0; i < names.length - 1; i += 1) {
        const [x0, y0] = pts[names[i]];
        const [x1, y1] = pts[names[i + 1]];
        if (y <= y1 || i === names.length - 2) {
            if (Math.abs(y1 - y0) < 1e-9) return Math.min(x0, x1);
            const t = Math.min(1, Math.max(0, (y - y0) / (y1 - y0)));
            return x0 + (x1 - x0) * t;
        }
    }
    return pts[names[0]][0];
};

/**
 * Empurra a fronteira da célula do texto para a direita até conter os retângulos (já com folga),
 * comprimindo a região à direita proporcionalmente (ordem preservada, borda direita fixa).
 */
export const enforceKeepoutPattern = (pts, names, rects) => {
    for (let iter = 0; iter < 8; iter += 1) {
        let delta = 0;
        rects.forEach((r) => {
            const ys = [r.y1, r.y2, ...names.map((n) => pts[n][1])].filter((y) => y >= r.y1 - 1e-9 && y <= r.y2 + 1e-9);
            ys.forEach((y) => {
                delta = Math.max(delta, r.x2 - boundaryX(pts, names, y));
            });
        });
        if (delta <= 1e-6) return;
        const xb = Math.min(...names.map((n) => pts[n][0]));
        Object.keys(pts).forEach((n) => {
            const [x, y] = pts[n];
            if (x >= xb - 1e-9 && x < 1 - 1e-9) {
                pts[n] = [x + ((delta + 1e-4) * (1 - x)) / (1 - xb), y];
            }
        });
    }
};

/**
 * Pontos do padrão no instante t. `drift: [amp, período, fase]` move x; `driftY` move y.
 * `pointer` (normalizado, sem transpor) empurra pontos internos de leve; `pushScale` amortece.
 */
export const patternPoints = (def, t = 0, { pointer = null, portrait = false, keepout = [], pushScale = 1, still = false } = {}) => {
    const pts = {};
    Object.entries(def.points).forEach(([name, d]) => {
        let [x, y] = d.p;
        if (!d.fixed && !still) {
            if (d.drift) x += Math.sin((t * Math.PI * 2) / d.drift[1] + d.drift[2]) * d.drift[0];
            if (d.driftY) y += Math.sin((t * Math.PI * 2) / d.driftY[1] + d.driftY[2]) * d.driftY[0];
            if (pointer && !d.edge && def.push) {
                const dx = x - pointer.x;
                const dy = y - pointer.y;
                const dist = Math.hypot(dx, dy);
                if (dist > 1e-6 && dist < 0.5) {
                    const push = def.push * pushScale * (1 - smoothstep(0, 0.5, dist));
                    x += (dx / dist) * push;
                    y += (dy / dist) * push;
                }
            }
        }
        pts[name] = [x, y];
    });
    enforceKeepoutPattern(pts, def.boundary, keepout);
    const out = {};
    Object.entries(pts).forEach(([n, [x, y]]) => {
        out[n] = portrait ? [y, x] : [x, y];
    });
    return out;
};

export const patternCells = (def, t, opts) => {
    const pts = patternPoints(def, t, opts);
    return def.cells.map((names) => names.map((n) => pts[n]));
};

/** Geometria do painel dentro do Hero (mesmas regras da Hero atual). */
export const panelGeometry = (W, H) => {
    const portrait = W < 700;
    const fade = portrait ? 32 : 48;
    const mx = portrait ? 16 : W * 0.055;
    const my = portrait ? 16 : (H - fade) * 0.085;
    const pw = W - mx * 2;
    const ph = H - fade - my * 2;
    const S = pw / 1730;
    return { W, H, portrait, fade, mx, my, pw, ph, S, Sg: Math.max(S, 0.42), left: mx, top: my };
};

/* ---------- Contraste (WCAG) ---------- */

const lin = (v) => {
    const c = v / 255;
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
};
export const luminance = ([r, g, b]) => 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
export const contrastRatio = (a, b) => {
    const la = luminance(a);
    const lb = luminance(b);
    return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
};
export const hexRgb = (hex) => [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16));

/** Pior contraste de uma cor de tinta contra um conjunto de amostras de fundo (rgb). */
export const worstContrast = (ink, samples) => {
    let worst = Infinity;
    let bg = null;
    samples.forEach((s) => {
        const r = contrastRatio(ink, s);
        if (r < worst) {
            worst = r;
            bg = s;
        }
    });
    return { ratio: worst, bg };
};
