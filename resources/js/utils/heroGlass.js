/**
 * Lógica pura do Hero "Designed in glass" (sem three.js / DOM) — testável com node --test.
 * Coordenadas normalizadas do painel: x para a direita, y para baixo, 0..1.
 */

export const REF_WIDTH = 1730;
export const GAP = 14;
export const CORNER_RADIUS = 20;
export const DEPTH = 13;
export const BEVEL = 2.6;

const smoothstep = (a, b, v) => {
    const t = Math.min(1, Math.max(0, (v - a) / (b - a)));
    return t * t * (3 - 2 * t);
};

export const clamp = (v, lo, hi) => Math.min(hi, Math.max(lo, v));

export const easeOutBack = (t, s = 1.4) => {
    const x = clamp(t, 0, 1) - 1;
    return 1 + (s + 1) * x * x * x + s * x * x;
};

/** Passo de mola semi-implícito (k = rigidez, c = amortecimento). */
export const springStep = (state, target, dt, k = 170, c = 19) => {
    const steps = Math.max(1, Math.ceil(dt / 0.008));
    const h = dt / steps;
    let { x, v } = state;
    for (let i = 0; i < steps; i += 1) {
        v += (-k * (x - target) - c * v) * h;
        x += v * h;
    }
    state.x = x;
    state.v = v;
    return state;
};

/**
 * Pontos base do padrão de corte. A célula A (hero) fica larga o bastante para conter todo o texto
 * e os CTAs; os pontos da borda de A (tAB, J0, bAD) derivam menos e só são empurrados para a direita.
 */
const POINTS = {
    TL: { p: [0, 0], fixed: true },
    TR: { p: [1, 0], fixed: true },
    BR: { p: [1, 1], fixed: true },
    BL: { p: [0, 1], fixed: true },
    tAB: { p: [0.56, 0], edge: 'x', period: 13, phase: 0.4, drift: 0.02, rightOnly: true },
    tBC: { p: [0.72, 0], edge: 'x', period: 17, phase: 2.1 },
    tCE: { p: [0.86, 0], edge: 'x', period: 11, phase: 4.0 },
    bAD: { p: [0.47, 1], edge: 'x', period: 15, phase: 1.3, drift: 0.02, rightOnly: true },
    bDE: { p: [0.84, 1], edge: 'x', period: 19, phase: 5.2 },
    J0: { p: [0.5, 0.72], period: 14, phase: 0.9, drift: 0.02, rightOnly: true },
    J1: { p: [0.6, 0.62], period: 18, phase: 3.3 },
    J2: { p: [0.76, 0.49], period: 12, phase: 5.8 },
};

export const CELL_DEFS = [
    ['TL', 'tAB', 'J0', 'bAD', 'BL'], // A (hero)
    ['tAB', 'tBC', 'J1', 'J0'], // B
    ['tBC', 'tCE', 'J2', 'J1'], // C
    ['J0', 'J1', 'J2', 'bDE', 'bAD'], // D
    ['tCE', 'TR', 'BR', 'bDE', 'J2'], // E
];

const MIN_SEP = 0.05;

/** x da borda direita de A (tAB → J0 → bAD) na altura y. */
const edgeX = (p, y) => {
    const [x0, y0] = p.tAB;
    const [x1, y1] = p.J0;
    const [x2, y2] = p.bAD;
    if (y <= y1) return x0 + ((x1 - x0) * (y - y0)) / (y1 - y0);
    return x1 + ((x2 - x1) * (y - y1)) / (y2 - y1);
};

/** Desloca a borda de A para a direita até conter todos os retângulos (já com folga). */
const enforceKeepout = (p, rects) => {
    let delta = 0;
    rects.forEach((r) => {
        [r.y1, r.y2, p.J0[1]]
            .filter((y) => y >= r.y1 && y <= r.y2)
            .forEach((y) => {
                delta = Math.max(delta, r.x2 - edgeX(p, y));
            });
    });
    if (delta > 0) {
        ['tAB', 'J0', 'bAD'].forEach((n) => {
            p[n][0] += delta;
        });
    }
};

/** Mantém a ordem dos pontos nas bordas e entre as junções (sem células degeneradas). */
const enforceOrder = (p) => {
    p.tBC[0] = Math.max(p.tBC[0], p.tAB[0] + MIN_SEP);
    p.tCE[0] = Math.max(p.tCE[0], p.tBC[0] + MIN_SEP);
    p.tCE[0] = Math.min(p.tCE[0], 0.97);
    p.bDE[0] = Math.max(p.bDE[0], p.bAD[0] + MIN_SEP);
    p.J1[0] = Math.max(p.J1[0], p.J0[0] + MIN_SEP);
    p.J2[0] = Math.max(p.J2[0], p.J1[0] + MIN_SEP);
};

/**
 * Retângulos (px do painel) → retângulos normalizados com folga `clearance` px, no espaço do padrão
 * (antes da transposição do retrato).
 */
export const keepoutRects = (boxes, pw, ph, clearance, portrait) =>
    boxes.map((b) => {
        const x1 = (b.x - clearance) / pw;
        const x2 = (b.x + b.w + clearance) / pw;
        const y1 = (b.y - clearance) / ph;
        const y2 = (b.y + b.h + clearance) / ph;
        return portrait ? { x1: y1, x2: y2, y1: x1, y2: x2 } : { x1, x2, y1, y2 };
    });

/**
 * Pontos do padrão no instante t (s): deriva senoidal + empurrão do cursor + keepout do texto.
 * `pointer` em coordenadas normalizadas do painel (ou null). `portrait` transpõe (x↔y).
 * `keepout` vem de keepoutRects().
 */
export const heroPoints = (t, pointer = null, portrait = false, keepout = [], pushScale = 1) => {
    const p = {};
    Object.entries(POINTS).forEach(([name, def]) => {
        let [x, y] = def.p;
        if (!def.fixed) {
            const w = (Math.PI * 2) / def.period;
            const amp = def.drift ?? 0.03;
            x += Math.sin(t * w + def.phase) * amp;
            if (!def.edge) {
                y += Math.sin(t * w * 0.83 + def.phase * 1.7) * 0.05;
            }
            if (pointer) {
                const dx = x - pointer.x;
                const dy = y - pointer.y;
                const d = Math.hypot(dx, dy);
                if (d < 0.6 && d > 1e-6) {
                    const push = 0.05 * pushScale * (1 - smoothstep(0, 0.6, d));
                    const px = (dx / d) * push;
                    x += def.rightOnly ? Math.max(0, px) : px;
                    if (!def.edge) {
                        y += (dy / d) * push;
                    }
                }
            }
        }
        p[name] = [x, y];
    });
    enforceKeepout(p, keepout);
    enforceOrder(p);
    const out = {};
    Object.entries(p).forEach(([name, [x, y]]) => {
        out[name] = portrait ? [y, x] : [x, y];
    });
    return out;
};

export const heroCells = (t, pointer = null, portrait = false, keepout = [], pushScale = 1) => {
    const p = heroPoints(t, pointer, portrait, keepout, pushScale);
    return CELL_DEFS.map((names) => names.map((n) => p[n]));
};

export const polygonArea = (pts) => {
    let a = 0;
    for (let i = 0; i < pts.length; i += 1) {
        const [x1, y1] = pts[i];
        const [x2, y2] = pts[(i + 1) % pts.length];
        a += x1 * y2 - x2 * y1;
    }
    return a / 2;
};

export const polygonCentroid = (pts) => {
    let cx = 0;
    let cy = 0;
    pts.forEach(([x, y]) => {
        cx += x;
        cy += y;
    });
    return [cx / pts.length, cy / pts.length];
};

/** Recua o contorno de d px para dentro (miter limitado). */
export const insetPolygon = (pts, d) => {
    const n = pts.length;
    const sign = polygonArea(pts) > 0 ? 1 : -1;
    const lines = [];
    for (let i = 0; i < n; i += 1) {
        const [x1, y1] = pts[i];
        const [x2, y2] = pts[(i + 1) % n];
        const len = Math.hypot(x2 - x1, y2 - y1) || 1;
        const ux = (x2 - x1) / len;
        const uy = (y2 - y1) / len;
        // normal para dentro (y-down: área positiva = horário na tela)
        const nx = -uy * sign;
        const ny = ux * sign;
        lines.push({ px: x1 + nx * d, py: y1 + ny * d, ux, uy });
    }
    return pts.map((pt, i) => {
        const a = lines[(i + n - 1) % n];
        const b = lines[i];
        const cross = a.ux * b.uy - a.uy * b.ux;
        if (Math.abs(cross) < 1e-6) {
            return [b.px, b.py];
        }
        const t = ((b.px - a.px) * b.uy - (b.py - a.py) * b.ux) / cross;
        let x = a.px + a.ux * t;
        let y = a.py + a.uy * t;
        const mx = x - pt[0];
        const my = y - pt[1];
        const m = Math.hypot(mx, my);
        const maxM = Math.abs(d) * 2.5;
        if (m > maxM) {
            x = pt[0] + (mx / m) * maxM;
            y = pt[1] + (my / m) * maxM;
        }
        return [x, y];
    });
};

/**
 * Pontos de tangência/controle dos cantos arredondados:
 * [{ from:[x,y], ctrl:[x,y], to:[x,y] }] — um por vértice.
 */
export const roundedCorners = (pts, radius) => {
    const n = pts.length;
    return pts.map((p, i) => {
        const prev = pts[(i + n - 1) % n];
        const next = pts[(i + 1) % n];
        const v1 = [prev[0] - p[0], prev[1] - p[1]];
        const v2 = [next[0] - p[0], next[1] - p[1]];
        const l1 = Math.hypot(...v1) || 1;
        const l2 = Math.hypot(...v2) || 1;
        const cos = clamp((v1[0] * v2[0] + v1[1] * v2[1]) / (l1 * l2), -1, 1);
        const ang = Math.acos(cos);
        const ideal = ang < 1e-3 ? 0 : radius / Math.tan(ang / 2);
        const dist = Math.min(ideal, l1 * 0.48, l2 * 0.48);
        return {
            from: [p[0] + (v1[0] / l1) * dist, p[1] + (v1[1] / l1) * dist],
            ctrl: p,
            to: [p[0] + (v2[0] / l2) * dist, p[1] + (v2[1] / l2) * dist],
        };
    });
};

/** Homografia 4 pontos → 4 pontos (h33 = 1) por eliminação gaussiana. */
export const solveHomography = (src, dst) => {
    const A = [];
    const b = [];
    for (let i = 0; i < 4; i += 1) {
        const [x, y] = src[i];
        const [u, v] = dst[i];
        A.push([x, y, 1, 0, 0, 0, -x * u, -y * u]);
        b.push(u);
        A.push([0, 0, 0, x, y, 1, -x * v, -y * v]);
        b.push(v);
    }
    const n = 8;
    for (let c = 0; c < n; c += 1) {
        let piv = c;
        for (let r = c + 1; r < n; r += 1) {
            if (Math.abs(A[r][c]) > Math.abs(A[piv][c])) piv = r;
        }
        [A[c], A[piv]] = [A[piv], A[c]];
        [b[c], b[piv]] = [b[piv], b[c]];
        const d = A[c][c] || 1e-12;
        for (let r = c + 1; r < n; r += 1) {
            const f = A[r][c] / d;
            for (let k = c; k < n; k += 1) A[r][k] -= f * A[c][k];
            b[r] -= f * b[c];
        }
    }
    const h = new Array(n).fill(0);
    for (let r = n - 1; r >= 0; r -= 1) {
        let s = b[r];
        for (let k = r + 1; k < n; k += 1) s -= A[r][k] * h[k];
        h[r] = s / (A[r][r] || 1e-12);
    }
    return h;
};

export const applyHomography = (h, [x, y]) => {
    const w = h[6] * x + h[7] * y + 1;
    return [(h[0] * x + h[1] * y + h[2]) / w, (h[3] * x + h[4] * y + h[5]) / w];
};

/** matrix3d (CSS, coluna-major) a partir da homografia. */
export const homographyToMatrix3d = (h) =>
    `matrix3d(${[h[0], h[3], 0, h[6], h[1], h[4], 0, h[7], 0, 0, 1, 0, h[2], h[5], 0, 1].join(',')})`;

/** Quebra `text` em linhas que cabem em `maxWidth` (measure injeta a medição). */
export const wrapLines = (text, maxWidth, measure) => {
    const words = text.split(/\s+/).filter(Boolean);
    const lines = [];
    let cur = '';
    words.forEach((w) => {
        const next = cur ? `${cur} ${w}` : w;
        if (cur && measure(next) > maxWidth) {
            lines.push(cur);
            cur = w;
        } else {
            cur = next;
        }
    });
    if (cur) lines.push(cur);
    return lines;
};

/**
 * Layout do conteúdo impresso no painel (px do painel). `measure(text, size, weight, tracking)` → largura.
 * Fonte única para o canvas e para a camada DOM, para que ambos coincidam.
 */
export const heroLayout = ({ pw, ph, S, portrait, content, measure }) => {
    const u = Math.max(S, 0.62);
    const padX = portrait ? pw * 0.07 : pw * 0.05;
    const textW = portrait ? pw - padX * 2 : pw * 0.44 - padX;
    const eyebrowSize = Math.max(11 * S, 10);
    const eyebrowTracking = 0.2 * eyebrowSize;
    const eyebrowLines = wrapLines(content.eyebrow.toUpperCase(), textW, (t) =>
        measure(t, eyebrowSize, 600, eyebrowTracking),
    );
    const eyebrowLh = eyebrowSize * 1.5;
    const eyebrow = {
        lines: eyebrowLines,
        size: eyebrowSize,
        lh: eyebrowLh,
        tracking: eyebrowTracking,
        x: padX,
        y: ph * 0.135,
    };

    const maxLines = portrait ? 0.3 : 0.46;
    let size = Math.min(pw * 0.078, ph * 0.2);
    let lines;
    let lh;
    for (;;) {
        const tracking = -0.045 * size;
        lines = wrapLines(content.title, textW, (t) => measure(t, size, 600, tracking));
        lh = size * 0.97;
        const widest = Math.max(...lines.map((l) => measure(l, size, 600, tracking)));
        if ((lines.length * lh <= ph * maxLines && widest <= textW) || size <= 22) break;
        size *= 0.94;
    }
    const headline = { lines, size, lh, tracking: -0.045 * size, x: padX, y: eyebrow.y + eyebrowLines.length * eyebrowLh + eyebrowSize * 1.2 };

    const subSize = Math.max(16 * u, 14);
    const subLines = wrapLines(content.subtitle, textW, (t) => measure(t, subSize, 400, 0));
    const subtitle = {
        lines: subLines,
        size: subSize,
        lh: subSize * 1.45,
        x: padX,
        y: headline.y + lines.length * lh + subSize * 1.1,
    };

    const widest = (arr, sz, weight, tracking) => Math.max(...arr.map((l) => measure(l, sz, weight, tracking)));
    const headlineW = widest(lines, size, 600, -0.045 * size);
    const subtitleW = widest(subLines, subSize, 400, 0);
    // Borda direita do bloco de texto (título/subtítulo): a linha de CTAs termina nela.
    const blockRight = padX + Math.min(textW, Math.max(headlineW, subtitleW));

    const ctaH = Math.max(40 * S, 40);
    const ctaSize = Math.max(14 * u, 13);
    const gap = 26;
    const primaryW = Math.max(116 * Math.max(S, 1), measure(content.ctas[0].label, ctaSize, 600, 0) + 44);
    const items = [
        { ...content.ctas[0], kind: 'primary', w: primaryW },
        ...content.ctas.slice(1).map((c) => ({ ...c, kind: 'link', w: measure(c.label, ctaSize, 500, 0) })),
    ];
    // Linhas: no retrato o primário ocupa a largura do bloco e os links vêm abaixo, um em cada ponta;
    // no desktop/tablet, quebra gulosa com cada linha alinhada à direita do bloco.
    const rows = [];
    if (portrait) {
        items[0].w = blockRight - padX;
        rows.push([items[0]]);
        const rest = items.slice(1);
        const restW = rest.reduce((acc, c) => acc + c.w, 0) + gap * (rest.length - 1);
        if (restW <= blockRight - padX) rows.push(rest);
        else rest.forEach((c) => rows.push([c]));
    } else {
        items.forEach((c) => {
            const row = rows[rows.length - 1];
            const rowW = row ? row.reduce((acc, r) => acc + r.w, 0) + gap * row.length : Infinity;
            if (row && rowW + c.w <= blockRight - padX) row.push(c);
            else rows.push([c]);
        });
    }
    const rowGap = 6;
    const ctaY = Math.min(
        subtitle.y + subLines.length * subtitle.lh + ctaH * 0.7,
        ph * (portrait ? 0.62 : 0.86) - ctaH - (rows.length - 1) * (ctaH + rowGap),
    );
    const ctas = [];
    rows.forEach((row, ri) => {
        const y = ctaY + ri * (ctaH + rowGap);
        if (portrait && row.length > 1) {
            // dois links: um na borda esquerda, outro na direita do bloco
            row.forEach((c, ci) => {
                const x = ci === 0 ? padX : blockRight - c.w;
                ctas.push({ ...c, x, y, h: ctaH, size: ctaSize });
            });
            return;
        }
        const rowW = row.reduce((acc, c) => acc + c.w, 0) + gap * (row.length - 1);
        let x = Math.max(padX, blockRight - rowW);
        row.forEach((c) => {
            ctas.push({ ...c, x, y, h: ctaH, size: ctaSize });
            x += c.w + gap;
        });
    });

    const image = portrait
        ? { x: 0, y: ph * 0.5, w: pw, h: ph * 0.5 }
        : { x: pw * 0.5, y: 0, w: pw * 0.5, h: ph };

    const keepout = [
        { x: eyebrow.x, y: eyebrow.y, w: widest(eyebrowLines, eyebrowSize, 600, eyebrowTracking), h: eyebrowLines.length * eyebrowLh },
        { x: headline.x, y: headline.y, w: headlineW, h: lines.length * lh },
        { x: subtitle.x, y: subtitle.y, w: subtitleW, h: subLines.length * subtitle.lh },
        ...ctas.map((c) => ({ x: c.x, y: c.y, w: c.w, h: c.h })),
    ];

    return { eyebrow, headline, subtitle, ctas, image, padX, blockRight, keepout };
};

/** Centro da caixa envolvente (pivô de inclinação de cada peça). */
export const boxCenter = (pts) => {
    let x0 = Infinity;
    let y0 = Infinity;
    let x1 = -Infinity;
    let y1 = -Infinity;
    pts.forEach(([x, y]) => {
        x0 = Math.min(x0, x);
        y0 = Math.min(y0, y);
        x1 = Math.max(x1, x);
        y1 = Math.max(y1, y);
    });
    return [(x0 + x1) / 2, (y0 + y1) / 2];
};

/**
 * "Calma" (0..1) perto dos CTAs: 1 dentro de `inner` px da caixa mais próxima, 0 além de `outer` px.
 * Usada para amortecer o efeito do vidro antes de o cursor chegar ao botão (o alvo não foge do mouse).
 */
export const calmNear = (px, py, boxes, inner = 24, outer = 110) => {
    let d = Infinity;
    boxes.forEach((b) => {
        const dx = Math.max(b.x - px, 0, px - (b.x + b.w));
        const dy = Math.max(b.y - py, 0, py - (b.y + b.h));
        d = Math.min(d, Math.hypot(dx, dy));
    });
    return 1 - smoothstep(inner, outer, d);
};
