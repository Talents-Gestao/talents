import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    PATTERNS,
    contrastRatio,
    hexRgb,
    panelGeometry,
    patternCells,
    worstContrast,
} from '../../resources/js/Components/Landing/hero/patterns.js';
import { adaptQuality, detectTier, percentile } from '../../resources/js/Components/Landing/hero/quality.js';
import { heroLayout, keepoutRects, polygonArea } from '../../resources/js/utils/heroGlass.js';

const content = {
    eyebrow: 'Consultoria estratégica em gestão de pessoas',
    title: 'O resultado que você espera está nas pessoas!',
    subtitle: 'Potencialize resultados com gestão de pessoas, ciência e estratégia.',
    ctas: [{ label: 'Para Empresas' }, { label: 'Para Pessoas' }, { label: 'Falar com Especialista' }],
};
const measure = (t, size, w, tr = 0) => t.length * (size * 0.53 + tr);

const segDist = ([px, py], [ax, ay], [bx, by]) => {
    const dx = bx - ax;
    const dy = by - ay;
    const k = Math.min(1, Math.max(0, ((px - ax) * dx + (py - ay) * dy) / (dx * dx + dy * dy || 1)));
    return Math.hypot(px - (ax + k * dx), py - (ay + k * dy));
};
const inside = ([x, y], poly) => {
    let c = false;
    for (let i = 0, j = poly.length - 1; i < poly.length; j = i, i += 1) {
        const [xi, yi] = poly[i];
        const [xj, yj] = poly[j];
        if (yi > y !== yj > y && x < ((xj - xi) * (y - yi)) / (yj - yi) + xi) c = !c;
    }
    return c;
};

const VIEWPORTS = [
    ['1440', 1440, 768],
    ['1024', 1024, 668],
    ['768', 768, 668],
    ['390', 390, 812],
    ['360', 360, 812],
];

describe('direções da Hero: texto e CTAs inteiros na peça do texto (folga >= 16px)', () => {
    Object.entries(PATTERNS).forEach(([id, def]) => {
        VIEWPORTS.forEach(([name, W, H]) => {
            it(`direção ${id.toUpperCase()} em ${name}px`, () => {
                const g = panelGeometry(W, H);
                const L = heroLayout({ pw: g.pw, ph: g.ph, S: g.S, portrait: g.portrait, content, measure });
                const inset = (7 + 2.6) * g.Sg;
                const keep = keepoutRects(L.keepout, g.pw, g.ph, (16 + inset) * 1.2, g.portrait);
                for (let t = 0; t < 40; t += 1.7) {
                    [null, { x: 0.45, y: 0.6 }, { x: 0.6, y: 0.3 }].forEach((pointer) => {
                        const cells = patternCells(def, t, { portrait: g.portrait, keepout: keep, pointer, pushScale: 1 });
                        cells.forEach((c) => assert.ok(Math.abs(polygonArea(c)) > 0.002, `${id}/${name}: célula degenerada`));
                        const text = cells[0].map(([x, y]) => [x * g.pw, y * g.ph]);
                        const others = cells.slice(1).map((c) => c.map(([x, y]) => [x * g.pw, y * g.ph]));
                        L.keepout.forEach((b) => {
                            const corners = [
                                [b.x, b.y],
                                [b.x + b.w, b.y],
                                [b.x + b.w, b.y + b.h],
                                [b.x, b.y + b.h],
                            ];
                            corners.forEach((pt) => {
                                assert.ok(inside(pt, text), `${id}/${name} t=${t}: canto fora da peça do texto`);
                                others.forEach((poly) => assert.ok(!inside(pt, poly)));
                                // distância às arestas internas da peça do texto (as que não são borda do painel)
                                for (let i = 0; i < text.length; i += 1) {
                                    const a = text[i];
                                    const c = text[(i + 1) % text.length];
                                    const onEdge =
                                        (Math.abs(a[0] - c[0]) < 1e-6 && (a[0] < 1e-6 || Math.abs(a[0] - g.pw) < 1e-6)) ||
                                        (Math.abs(a[1] - c[1]) < 1e-6 && (a[1] < 1e-6 || Math.abs(a[1] - g.ph) < 1e-6));
                                    if (!onEdge) {
                                        const d = segDist(pt, a, c);
                                        assert.ok(d >= 16 + inset, `${id}/${name} t=${t}: folga ${d.toFixed(1)}px`);
                                    }
                                }
                            });
                        });
                    });
                }
            });
        });
    });

    // Altura do .hs: clamp(620px, 50vw, 780px) + 48px (desktop/tablet) e 780px + 32px (retrato).
    const heroH = (W) => (W < 768 ? 812 : Math.min(780, Math.max(620, W / 2)) + 48);
    const bottom = (b) => b.y + b.h;

    it('subtítulo e CTAs não se sobrepõem e as CTAs ficam no painel, de 320 a 1600px', () => {
        // medida mais larga que a Figtree real, para cobrir o pior caso de quebra
        const wide = (t, size, w, tr = 0) => t.length * (size * (w >= 600 ? 0.58 : 0.55) + tr);
        for (let W = 320; W <= 1600; W += 8) {
            [measure, wide].forEach((m) => {
                const g = panelGeometry(W, heroH(W));
                const L = heroLayout({ pw: g.pw, ph: g.ph, S: g.S, portrait: g.portrait, content, measure: m });
                const subBottom = L.subtitle.y + L.subtitle.lines.length * L.subtitle.lh;
                const headBottom = L.headline.y + L.headline.lines.length * L.headline.lh;
                assert.ok(L.subtitle.y >= headBottom, `${W}px: subtítulo sobre o título`);
                L.ctas.forEach((c) => {
                    assert.ok(c.y >= subBottom + 8, `${W}px: CTA "${c.label}" em ${c.y.toFixed(0)} sobre o subtítulo (até ${subBottom.toFixed(0)})`);
                    assert.ok(bottom(c) <= g.ph, `${W}px: CTA "${c.label}" fora do painel`);
                    // retrato: a foto (rosto no topo) começa abaixo das CTAs, não sob a peça do texto
                    if (g.portrait) assert.ok(L.image.y >= bottom(c) + 24, `${W}px: foto começa sob as CTAs`);
                });
            });
        }
    });

    it('A tem uma peça calma para o texto e 7 facetas de asa', () => {
        assert.equal(PATTERNS.a.cells.length, 8);
        assert.equal(PATTERNS.c.cells.length, 3);
    });
});

describe('níveis de qualidade', () => {
    it('detecta baixo com movimento reduzido, save-data ou aparelho fraco', () => {
        assert.equal(detectTier({ reducedMotion: true }), 'low');
        assert.equal(detectTier({ saveData: true }), 'low');
        assert.equal(detectTier({ deviceMemory: 2 }), 'low');
        assert.equal(detectTier({ webgl: false }), 'low');
    });

    it('alto só com WebGL2, memória/núcleos altos e ponteiro fino', () => {
        assert.equal(detectTier({}), 'high');
        assert.equal(detectTier({ coarsePointer: true }), 'medium');
        assert.equal(detectTier({ webgl2: false }), 'medium');
        assert.equal(detectTier({ deviceMemory: 4, cores: 8 }), 'medium');
    });

    it('reduz DPR antes de rebaixar o nível, e só rebaixa no DPR 1', () => {
        assert.deepEqual(adaptQuality({ tier: 'high', dpr: 2, p95: 12 }), { tier: 'high', dpr: 2, changed: false });
        assert.equal(adaptQuality({ tier: 'high', dpr: 2, p95: 40 }).dpr, 1.75);
        assert.equal(adaptQuality({ tier: 'high', dpr: 1, p95: 40 }).tier, 'medium');
        assert.equal(adaptQuality({ tier: 'medium', dpr: 1, p95: 60 }).tier, 'low');
    });

    it('percentil', () => {
        assert.equal(percentile([10, 20, 30, 40, 50, 60, 70, 80, 90, 100], 0.95), 100);
        assert.equal(percentile([5, 1, 3], 0.5), 3);
    });
});

describe('contraste (WCAG)', () => {
    it('razões conhecidas', () => {
        assert.equal(contrastRatio([0, 0, 0], [255, 255, 255]).toFixed(1), '21.0');
        assert.ok(contrastRatio(hexRgb('#632a7e'), [255, 255, 255]) > 9);
    });

    it('pior contraste contra várias amostras', () => {
        const r = worstContrast(hexRgb('#2a1042'), [
            [250, 250, 250],
            [212, 184, 228],
        ]);
        assert.deepEqual(r.bg, [212, 184, 228]);
        assert.ok(r.ratio > 4.5);
    });
});
