import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    applyHomography,
    boxCenter,
    calmNear,
    easeOutBack,
    heroCells,
    heroLayout,
    insetPolygon,
    keepoutRects,
    polygonArea,
    solveHomography,
    springStep,
    wrapLines,
} from '../../resources/js/utils/heroGlass.js';

const close = (a, b, eps = 1e-6) => assert.ok(Math.abs(a - b) < eps, `${a} ≉ ${b}`);

describe('heroCells', () => {
    it('gera 5 células com os vértices esperados', () => {
        const cells = heroCells(0);
        assert.deepEqual(cells.map((c) => c.length), [5, 4, 4, 5, 5]);
        assert.deepEqual(cells[0][0], [0, 0]);
    });

    it('a deriva fica dentro de ±.03 em x e ±.05 em y e cantos não se movem', () => {
        for (let t = 0; t < 40; t += 3.7) {
            const [a] = heroCells(t);
            assert.deepEqual(a[0], [0, 0]);
            close(a[2][0], 0.5, 0.031);
            close(a[2][1], 0.72, 0.051);
        }
    });

    it('o cursor empurra pontos próximos e cresce a célula sob ele', () => {
        const base = heroCells(0);
        const pushed = heroCells(0, { x: 0.3, y: 0.3 });
        assert.notDeepEqual(base[0][2], pushed[0][2]);
        const far = heroCells(0, { x: 5, y: 5 });
        assert.deepEqual(far, base);
    });

    it('pontos de borda ficam na borda mesmo com o cursor', () => {
        const cells = heroCells(1, { x: 0.5, y: 0.02 });
        cells[1].slice(0, 2).forEach(([, y]) => assert.equal(y, 0));
    });

    it('retrato transpõe x/y', () => {
        const land = heroCells(2, null, false);
        const port = heroCells(2, null, true);
        assert.deepEqual(port[0][2], [land[0][2][1], land[0][2][0]]);
    });
});

describe('insetPolygon', () => {
    it('reduz um quadrado pelo recuo em cada lado', () => {
        const sq = [[0, 0], [10, 0], [10, 10], [0, 10]];
        const out = insetPolygon(sq, 1);
        close(Math.abs(polygonArea(out)), 64);
    });
});

describe('homografia', () => {
    const src = [[0, 0], [100, 0], [100, 50], [0, 50]];

    it('mapeia os 4 cantos exatamente', () => {
        const dst = [[10, 20], [210, 5], [190, 140], [0, 120]];
        const h = solveHomography(src, dst);
        src.forEach((p, i) => {
            const q = applyHomography(h, p);
            close(q[0], dst[i][0], 1e-4);
            close(q[1], dst[i][1], 1e-4);
        });
    });

    it('identidade quando origem = destino', () => {
        const h = solveHomography(src, src);
        const q = applyHomography(h, [30, 20]);
        close(q[0], 30, 1e-6);
        close(q[1], 20, 1e-6);
    });
});

describe('mola e easing', () => {
    it('a mola converge ao alvo', () => {
        const s = { x: 0, v: 0 };
        for (let i = 0; i < 120; i += 1) springStep(s, 50, 1 / 60);
        close(s.x, 50, 0.05);
    });

    it('easeOutBack termina em 1 e ultrapassa no meio', () => {
        close(easeOutBack(1), 1);
        assert.ok(easeOutBack(0.7) > 1);
    });
});

describe('heroLayout', () => {
    const content = {
        eyebrow: 'Consultoria estratégica',
        title: 'O resultado que você espera está nas pessoas!',
        subtitle: 'Potencialize resultados com gestão de pessoas, ciência e estratégia.',
        ctas: [{ label: 'Para Empresas' }, { label: 'Para Pessoas' }, { label: 'Falar com Especialista' }],
    };
    const measure = (t, size) => t.length * size * 0.5;

    it('quebra linhas respeitando a largura', () => {
        const lines = wrapLines('aa bb cc dd', 5, (t) => t.length);
        assert.deepEqual(lines, ['aa bb', 'cc dd']);
    });

    it('mantém o título dentro da coluna e as CTAs dentro do painel', () => {
        const L = heroLayout({ pw: 1280, ph: 580, S: 0.74, portrait: false, content, measure });
        const widest = Math.max(...L.headline.lines.map((l) => measure(l, L.headline.size)));
        assert.ok(widest <= 1280 * 0.5);
        assert.ok(L.headline.lines.length * L.headline.lh <= 580 * 0.46 + 1);
        L.ctas.forEach((c) => assert.ok(c.y + c.h <= 580));
        assert.equal(L.ctas[0].kind, 'primary');
        assert.equal(L.ctas.length, 3);
    });

    // Geometria do painel como no resize() da HeroGlass; altura do .hero-glass:
    // clamp(620px, 50vw, 780px) + 48px (desktop/tablet) e 780px + 32px (retrato).
    const panel = (W) => {
        const portrait = W < 700;
        const H = W < 768 ? 812 : Math.min(780, Math.max(620, W / 2)) + 48;
        const fade = portrait ? 32 : 48;
        const mx = portrait ? 16 : W * 0.055;
        const my = portrait ? 16 : (H - fade) * 0.085;
        const pw = W - mx * 2;
        return { pw, ph: H - fade - my * 2, S: pw / 1730, portrait };
    };

    it('subtítulo e CTAs não se sobrepõem e a foto do retrato começa abaixo das CTAs, de 320 a 1600px', () => {
        const full = { ...content, eyebrow: 'Consultoria estratégica em gestão de pessoas' };
        // a segunda medida é mais larga que a Figtree real, para cobrir o pior caso de quebra
        const measures = [(t, size, w, tr = 0) => t.length * (size * 0.53 + tr), (t, size, w, tr = 0) => t.length * (size * (w >= 600 ? 0.58 : 0.55) + tr)];
        for (let W = 320; W <= 1600; W += 8) {
            measures.forEach((m) => {
                const g = panel(W);
                const L = heroLayout({ ...g, content: full, measure: m });
                const subBottom = L.subtitle.y + L.subtitle.lines.length * L.subtitle.lh;
                assert.ok(L.subtitle.y >= L.headline.y + L.headline.lines.length * L.headline.lh, `${W}px: subtítulo sobre o título`);
                L.ctas.forEach((c) => {
                    assert.ok(c.y >= subBottom + 8, `${W}px: CTA "${c.label}" sobre o subtítulo`);
                    assert.ok(c.y + c.h <= g.ph, `${W}px: CTA "${c.label}" fora do painel`);
                    if (g.portrait) assert.ok(L.image.y >= c.y + c.h + 24, `${W}px: foto começa sob as CTAs`);
                });
            });
        }
    });

    it('retrato empilha texto e imagem', () => {
        const L = heroLayout({ pw: 358, ph: 720, S: 0.2, portrait: true, content, measure });
        assert.ok(L.image.y > L.ctas[0].y);
        assert.equal(L.image.w, 358);
    });
});

describe('keepout: texto e CTAs ficam inteiros na célula A', () => {
    const content = {
        eyebrow: 'Consultoria estratégica em gestão de pessoas',
        title: 'O resultado que você espera está nas pessoas!',
        subtitle: 'Potencialize resultados com gestão de pessoas, ciência e estratégia.',
        ctas: [{ label: 'Para Empresas' }, { label: 'Para Pessoas' }, { label: 'Falar com Especialista' }],
    };
    const measure = (t, size, w, tr = 0) => t.length * (size * 0.52 + tr);
    const segDist = ([px, py], [ax, ay], [bx, by]) => {
        const dx = bx - ax;
        const dy = by - ay;
        const k = clamp01(((px - ax) * dx + (py - ay) * dy) / (dx * dx + dy * dy));
        return Math.hypot(px - (ax + k * dx), py - (ay + k * dy));
    };
    const clamp01 = (v) => Math.min(1, Math.max(0, v));

    const cases = [
        { name: 'desktop 1440', pw: 1281, ph: 598, S: 1281 / 1730, portrait: false },
        { name: 'desktop 1024', pw: 907, ph: 520, S: 907 / 1730, portrait: false },
        { name: 'tablet 768', pw: 684, ph: 515, S: 684 / 1730, portrait: false },
        { name: 'mobile 390', pw: 358, ph: 700, S: 358 / 1730, portrait: true },
        { name: 'mobile 360', pw: 328, ph: 700, S: 328 / 1730, portrait: true },
    ];

    cases.forEach(({ name, pw, ph, S, portrait }) => {
        it(`${name}: folga >= 16px dos cortes, em vários instantes e com o cursor perto`, () => {
            const L = heroLayout({ pw, ph, S, portrait, content, measure });
            const Sg = Math.max(S, 0.42);
            const inset = (14 / 2 + 2.6) * Sg;
            const keep = keepoutRects(L.keepout, pw, ph, (16 + inset) * 1.2, portrait);
            const pointers = [null];
            for (let x = 0; x <= 1; x += 0.125) for (let y = 0; y <= 1; y += 0.125) pointers.push({ x, y });
            for (let t = 0; t < 60; t += 2.3) {
                pointers.forEach((ptr) => {
                    const pp = ptr && portrait ? { x: ptr.y, y: ptr.x } : ptr;
                    const cells = heroCells(t, pp, portrait, keep, t % 2 < 1 ? 1 : 0.3);
                    cells.forEach((c) => assert.ok(Math.abs(polygonArea(c)) > 0.01, 'célula degenerada'));
                    const A = cells[0].map(([x, y]) => [x * pw, y * ph]);
                    const edges = [[A[1], A[2]], [A[2], A[3]]];
                    L.keepout.forEach((b) => {
                        [[b.x, b.y], [b.x + b.w, b.y], [b.x + b.w, b.y + b.h], [b.x, b.y + b.h]].forEach((corner) => {
                            edges.forEach(([a, bb]) => {
                                const d = segDist(corner, a, bb);
                                assert.ok(d >= 16 + inset, `${name} t=${t}: folga ${d.toFixed(1)}px`);
                            });
                            // lado de dentro de A: à esquerda (paisagem) ou acima (retrato) do corte
                            assert.ok(corner[0] <= Math.max(...A.map((q) => q[0])) + 1);
                        });
                    });
                });
            }
        });
    });
});

describe('CTAs alinhados à direita do bloco de texto', () => {
    const content = {
        eyebrow: 'Consultoria estratégica em gestão de pessoas',
        title: 'O resultado que você espera está nas pessoas!',
        subtitle: 'Potencialize resultados com gestão de pessoas, ciência e estratégia.',
        ctas: [{ label: 'Para Empresas' }, { label: 'Para Pessoas' }, { label: 'Falar com Especialista' }],
    };
    const measure = (t, size, w, tr = 0) => t.length * (size * 0.52 + tr);
    const right = (c) => c.x + c.w;

    [
        ['desktop 1440', 1281, 598, false],
        ['tablet 768', 684, 515, false],
    ].forEach(([name, pw, ph, portrait]) => {
        it(`${name}: a linha de CTAs termina na borda direita do bloco, na mesma ordem e sem sair dele`, () => {
            const L = heroLayout({ pw, ph, S: pw / 1730, portrait, content, measure });
            assert.deepEqual(L.ctas.map((c) => c.label), content.ctas.map((c) => c.label));
            const lastPerRow = new Map();
            L.ctas.forEach((c) => lastPerRow.set(c.y, c));
            lastPerRow.forEach((c) => assert.ok(Math.abs(right(c) - L.blockRight) < 0.5, `${name}: ${right(c)} ≠ ${L.blockRight}`));
            L.ctas.forEach((c) => assert.ok(c.x >= L.padX - 0.01 && right(c) <= L.blockRight + 0.5));
        });
    });

    it('mobile 390: primário na largura do bloco e links nas pontas, sem estourar', () => {
        const pw = 358;
        const L = heroLayout({ pw, ph: 700, S: pw / 1730, portrait: true, content, measure });
        const [primary, ...links] = L.ctas;
        assert.equal(primary.x, L.padX);
        assert.ok(Math.abs(right(primary) - L.blockRight) < 0.5);
        links.forEach((c) => assert.ok(c.x >= L.padX - 0.01 && right(c) <= pw - L.padX + 0.5));
        assert.ok(links.every((c) => c.y > primary.y));
    });
});

describe('amortecimento sobre os CTAs', () => {
    const box = [{ x: 100, y: 100, w: 120, h: 40 }];

    it('calma é 1 sobre/perto do CTA e 0 longe dele', () => {
        assert.equal(calmNear(150, 120, box), 1);
        assert.equal(calmNear(110, 80, box), 1);
        assert.equal(calmNear(600, 600, box), 0);
        const mid = calmNear(220 + 60, 120, box);
        assert.ok(mid > 0 && mid < 1);
    });

    it('empurrão dos cortes escala com pushScale', () => {
        const base = heroCells(3);
        const full = heroCells(3, { x: 0.62, y: 0.6 });
        const calm = heroCells(3, { x: 0.62, y: 0.6 }, false, [], 0.3);
        // J1 (C[3]) é empurrado; em y (sem a restrição de ordem do eixo x) o deslocamento cai para ~30%
        const shift = (a, b) => Math.abs(a[1] - b[1]);
        const dFull = shift(full[2][3], base[2][3]);
        const dCalm = shift(calm[2][3], base[2][3]);
        assert.ok(dFull > 0);
        assert.ok(dCalm < dFull * 0.35 + 1e-9);
    });

    it('pivô da peça é o centro da caixa envolvente', () => {
        assert.deepEqual(boxCenter([[0, 0], [10, 0], [10, 4], [2, 8]]), [5, 4]);
    });
});
