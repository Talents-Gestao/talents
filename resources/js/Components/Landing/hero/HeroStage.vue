<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, shallowRef } from 'vue';
import { heroLayout, insetPolygon, keepoutRects, roundedCorners } from '@/utils/heroGlass.js';
import { makeMeasure } from './pageCanvas.js';
import { PATTERNS, imageRect, panelGeometry, patternCells } from './patterns.js';
import { detectTier } from './quality.js';

/**
 * Hero em vidro — direções A (Metamorfose), B (Profundidade) e C (Luz), só em ambiente local (?hero=a|b|c).
 * Primeiro paint: composição estática em CSS/SVG com o texto real do DOM (também é o nível BAIXO e a
 * versão com movimento reduzido). O WebGL entra por cima com crossfade, sem deslocar nada.
 */
const props = defineProps({
    direction: { type: String, required: true },
    /** Indicador de desempenho (só chega true com APP_ENV=local e ?perf=1). */
    perf: { type: Boolean, default: false },
});

const CONTENT = {
    eyebrow: 'Consultoria estratégica em gestão de pessoas',
    title: 'O resultado que você espera está nas pessoas!',
    subtitle: 'Potencialize resultados com gestão de pessoas, ciência e estratégia.',
    ctas: [
        { label: 'Para Empresas', route: 'landing.empresas' },
        { label: 'Para Pessoas', route: 'landing.pessoas' },
        { label: 'Falar com Especialista', route: 'landing.contato' },
    ],
    image: {
        src: '/images/hero-home.png',
        alt: 'Profissional apresentando perfis comportamentais: Executor, Comunicador, Planejador e Analista',
    },
};

const SCENES = {
    a: () => import('./sceneMetamorfose.js'),
    b: () => import('./sceneProfundidade.js'),
    c: () => import('./sceneLuz.js'),
};

const rootEl = ref(null);
const canvasEl = ref(null);
const layerEl = ref(null);
const phase = ref('css'); // css → webgl (crossfade)
const tier = ref('medium');
const geo = shallowRef(null);
const layout = shallowRef(null);
const cssCells = shallowRef(null);
const hoverCta = ref(-1);
const perfInfo = ref(null);
let perfTimer = null;
let perfLt = { n: 0, max: 0 };
const TIER_LABEL = { high: 'alto', medium: 'médio', low: 'baixo (CSS)' };
const focusCta = ref(-1);

const state = { g: null, layout: null, keep: [], hover: -1, focus: -1, hasLS: true };
let engine = null;
let ro = null;
let disposed = false;
let measureApi = null;

const pattern = computed(() => PATTERNS[props.direction] ?? PATTERNS.a);

// ?adapt=0 desliga a qualidade adaptativa (só para medição; as direções só existem em ambiente local)
const queryAdapt = () => {
    try {
        return new URLSearchParams(window.location.search).get('adapt') !== '0';
    } catch {
        return true;
    }
};

const queryTier = () => {
    try {
        const t = new URLSearchParams(window.location.search).get('tier');
        return ['high', 'medium', 'low'].includes(t) ? t : null;
    } catch {
        return null;
    }
};

const probeWebgl = () => {
    try {
        const c = document.createElement('canvas');
        return { webgl: !!(c.getContext('webgl2') || c.getContext('webgl')), webgl2: !!document.createElement('canvas').getContext('webgl2') };
    } catch {
        return { webgl: false, webgl2: false };
    }
};

const reducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

const relayout = () => {
    const root = rootEl.value;
    if (!root || !measureApi) return;
    const g = panelGeometry(root.clientWidth, root.clientHeight);
    const L = heroLayout({ pw: g.pw, ph: g.ph, S: g.S, portrait: g.portrait, content: CONTENT, measure: measureApi.measure });
    const keep = keepoutRects(L.keepout, g.pw, g.ph, (16 + (7 + 2.6) * g.Sg) * 1.2, g.portrait);
    Object.assign(state, { g, layout: L, keep, hasLS: measureApi.hasLS });
    geo.value = g;
    layout.value = L;
    cssCells.value = patternCells(pattern.value, 0, { portrait: g.portrait, keepout: keep, still: true });
    engine?.resize();
};

/* ---------- composição CSS/SVG ---------- */
const cellPath = (cell, g) => {
    const pts = cell.map(([x, y]) => [x * g.pw, y * g.ph]);
    const inset = insetPolygon(pts, (7 + 2.6) * g.Sg);
    const corners = roundedCorners(inset, 20 * g.Sg);
    let d = `M${corners[0].to[0]},${corners[0].to[1]}`;
    for (let i = 1; i <= corners.length; i += 1) {
        const c = corners[i % corners.length];
        d += ` L${c.from[0]},${c.from[1]} Q${c.ctrl[0]},${c.ctrl[1]} ${c.to[0]},${c.to[1]}`;
    }
    return `${d} Z`;
};
const paths = computed(() => (cssCells.value && geo.value ? cssCells.value.map((c) => cellPath(c, geo.value)) : []));
const panelBox = computed(() => {
    const g = geo.value;
    return g ? { left: `${g.left}px`, top: `${g.top}px`, width: `${g.pw}px`, height: `${g.ph}px` } : {};
});
const clipId = computed(() => `hs-clip-${props.direction}`);
const photoRect = computed(() => (layout.value && geo.value ? imageRect(props.direction, layout.value, geo.value) : null));

/* ---------- camada de texto ---------- */
const layerStyle = computed(() => {
    const g = geo.value;
    if (!g) return {};
    return {
        width: `${g.pw}px`,
        height: `${g.ph}px`,
        transform: phase.value === 'webgl' ? undefined : `translate(${g.left}px, ${g.top}px)`,
    };
});
const box = (b) => ({ left: `${b.x}px`, top: `${b.y}px`, width: `${b.w}px`, height: `${b.h}px` });
const eyebrowStyle = computed(() => {
    const e = layout.value.eyebrow;
    return { left: `${e.x}px`, top: `${e.y}px`, fontSize: `${e.size}px`, lineHeight: `${e.lh}px`, letterSpacing: `${e.tracking}px` };
});
const headlineStyle = computed(() => {
    const h = layout.value.headline;
    return { left: `${h.x}px`, top: `${h.y}px`, fontSize: `${h.size}px`, lineHeight: `${h.lh}px`, letterSpacing: `${h.tracking}px` };
});
const subtitleStyle = computed(() => {
    const s = layout.value.subtitle;
    return { left: `${s.x}px`, top: `${s.y}px`, fontSize: `${s.size}px`, lineHeight: `${s.lh}px` };
});
const ctaStyle = (c) => ({ ...box(c), fontSize: `${c.size}px`, borderRadius: c.kind === 'primary' ? `${c.h / 2}px` : undefined });

const setCta = (hover, focus) => {
    if (hover !== undefined) hoverCta.value = hover;
    if (focus !== undefined) focusCta.value = focus;
    state.hover = hoverCta.value;
    state.focus = focusCta.value;
    engine?.redrawPage();
};
const onFocus = (i, e) => setCta(undefined, e.target.matches?.(':focus-visible') ? i : -1);

/* ---------- WebGL ---------- */
const loadImage = (src) =>
    new Promise((resolve) => {
        const img = new Image();
        img.onload = () => (img.decode ? img.decode().catch(() => {}).then(() => resolve(img)) : resolve(img));
        img.onerror = () => resolve(null);
        img.src = src;
    });

const startWebgl = async () => {
    const [THREE, { RoomEnvironment }, { createHeroEngine }, sceneMod, heroImg] = await Promise.all([
        import('three'),
        import('three/examples/jsm/environments/RoomEnvironment.js'),
        import('./engine.js'),
        SCENES[props.direction](),
        loadImage(CONTENT.image.src),
    ]);
    if (disposed || !canvasEl.value) return;
    engine = createHeroEngine({
        THREE,
        RoomEnvironment,
        root: rootEl.value,
        canvas: canvasEl.value,
        getLayer: () => layerEl.value,
        scene: sceneMod.default,
        state,
        heroImg,
        tier: tier.value,
        adapt: queryAdapt(),
        onFirstFrame: () => {
            phase.value = 'webgl';
        },
        onFail: (why) => {
            engine?.dispose();
            engine = null;
            phase.value = 'css';
            tier.value = 'low';
            window.__heroLab && (window.__heroLab.failure = why);
        },
    });
    await engine.ready;
    if (!disposed) engine?.kick();
};

onMounted(async () => {
    const gl = probeWebgl();
    const forced = queryTier();
    tier.value =
        forced ??
        detectTier({
            ...gl,
            deviceMemory: navigator.deviceMemory ?? 8,
            cores: navigator.hardwareConcurrency ?? 8,
            saveData: !!navigator.connection?.saveData,
            reducedMotion: reducedMotion(),
            coarsePointer: window.matchMedia?.('(pointer: coarse)').matches ?? false,
        });
    if (reducedMotion()) tier.value = 'low';

    // texto medido com a fonte do site (até 200 ms de espera; depois segue com a disponível)
    await Promise.race([document.fonts?.ready, new Promise((r) => setTimeout(r, 200))]);
    if (disposed) return;
    measureApi = makeMeasure();
    relayout();
    ro = new ResizeObserver(() => relayout());
    ro.observe(rootEl.value);
    document.fonts?.ready?.then(() => {
        if (!disposed) {
            measureApi = makeMeasure();
            relayout();
        }
    });

    window.__heroLab = {
        direction: props.direction,
        get tier() {
            return tier.value;
        },
        get phase() {
            return phase.value;
        },
        stats: () => engine?.lab.stats() ?? { tier: tier.value, webgl: false },
        sampleContrast: (cases) => engine?.lab.sampleContrast(cases) ?? null,
        beginCapture: () => engine?.lab.beginCapture(),
        renderAt: (t, pointer) => engine?.lab.renderAt(t, pointer),
        endCapture: () => engine?.lab.endCapture(),
    };

    if (props.perf) {
        // tarefas longas (> 50 ms) depois do carregamento, para conferir na GPU real
        const lt = { n: 0, max: 0 };
        const since = performance.now();
        try {
            new PerformanceObserver((list) => {
                list.getEntries().forEach((e) => {
                    if (e.startTime > since && e.duration > 50) {
                        lt.n += 1;
                        lt.max = Math.max(lt.max, Math.round(e.duration));
                    }
                });
            }).observe({ type: 'longtask', buffered: false });
        } catch {
            /* navegador sem longtask */
        }
        perfLt = lt;
        perfTimer = setInterval(() => {
            const s = engine?.lab.stats();
            perfInfo.value = s
                ? { fps: s.frame_p50 ? Math.round(1000 / s.frame_p50) : 0, p50: s.frame_p50, p95: s.frame_p95, cpu: s.cpu_p95, tier: TIER_LABEL[s.tier], dpr: s.dpr }
                : { fps: 0, p50: 0, p95: 0, cpu: 0, tier: TIER_LABEL[tier.value], dpr: '-' };
            perfInfo.value.lt = `${perfLt.n} (máx ${perfLt.max} ms)`;
        }, 500);
    }

    if (tier.value !== 'low') {
        await nextTick();
        startWebgl().catch(() => {
            phase.value = 'css';
            tier.value = 'low';
        });
    }
});

onBeforeUnmount(() => {
    disposed = true;
    clearInterval(perfTimer);
    ro?.disconnect();
    engine?.dispose();
    engine = null;
    if (window.__heroLab?.direction === props.direction) delete window.__heroLab;
});
</script>

<template>
    <div ref="rootEl" class="hs" :class="[`hs--${direction}`, { 'hs--webgl': phase === 'webgl' }]">
        <div class="hs__bg" aria-hidden="true" />

        <svg
            v-if="geo && paths.length"
            class="hs__css"
            :style="panelBox"
            :viewBox="`0 0 ${geo.pw} ${geo.ph}`"
            aria-hidden="true"
        >
            <defs>
                <clipPath :id="clipId">
                    <path v-for="(d, i) in paths.slice(1)" :key="i" :d="d" />
                </clipPath>
                <filter id="hs-shadow" x="-10%" y="-10%" width="130%" height="140%">
                    <feDropShadow dx="6" dy="10" stdDeviation="10" flood-color="#4a2070" flood-opacity="0.16" />
                </filter>
            </defs>
            <!-- B: a foto fica atrás do vidro -->
            <image
                v-if="direction === 'b' && photoRect"
                :href="CONTENT.image.src"
                :x="photoRect.x"
                :y="photoRect.y"
                :width="photoRect.w"
                :height="photoRect.h"
                preserveAspectRatio="xMaxYMin slice"
            />
            <path
                v-for="(d, i) in paths"
                :key="`s${i}`"
                :d="d"
                class="hs__shard"
                :class="{ 'hs__shard--text': i === 0, 'hs__shard--clear': direction === 'b' && i > 0 }"
                filter="url(#hs-shadow)"
            />
            <!-- A e C: a foto impressa nas peças, cortada pelas frestas -->
            <image
                v-if="direction !== 'b' && layout"
                :href="CONTENT.image.src"
                :x="layout.image.x"
                :y="layout.image.y"
                :width="layout.image.w"
                :height="layout.image.h"
                preserveAspectRatio="xMaxYMin slice"
                :clip-path="`url(#${clipId})`"
            />
        </svg>

        <canvas ref="canvasEl" class="hs__canvas" aria-hidden="true" />

        <output v-if="perf && perfInfo" class="hs__perf" aria-live="off">
            {{ direction.toUpperCase() }} · {{ perfInfo.fps }} fps · quadro p50 {{ perfInfo.p50 }} ms · p95 {{ perfInfo.p95 }} ms ·
            CPU p95 {{ perfInfo.cpu }} ms · {{ perfInfo.tier }} · DPR {{ perfInfo.dpr }} · tarefas &gt;50 ms: {{ perfInfo.lt }}
        </output>

        <div v-if="layout" ref="layerEl" class="hs__layer" :class="{ 'is-css': phase !== 'webgl' }" :style="layerStyle">
            <p class="hg-text hs-eyebrow" :style="eyebrowStyle">
                <span v-for="(line, i) in layout.eyebrow.lines" :key="i" class="block whitespace-pre">{{ line }}{{ ' ' }}</span>
            </p>
            <h1 class="hg-text hs-title" :style="headlineStyle">
                <span v-for="(line, i) in layout.headline.lines" :key="i" class="block whitespace-pre">{{ line }}{{ ' ' }}</span>
            </h1>
            <p class="hg-text hs-sub" :style="subtitleStyle">{{ CONTENT.subtitle }}</p>
            <img class="hs-img" :src="CONTENT.image.src" :alt="CONTENT.image.alt" :style="box(layout.image)" width="760" height="593" />
            <Link
                v-for="(c, i) in layout.ctas"
                :key="c.route"
                :href="route(c.route)"
                class="hg-cta"
                :class="c.kind === 'primary' ? 'hs-cta-primary' : 'hs-cta-link'"
                :style="ctaStyle(c)"
                @mouseenter="setCta(i)"
                @mouseleave="setCta(-1)"
                @focus="onFocus(i, $event)"
                @blur="setCta(undefined, -1)"
            >
                {{ c.label }}
            </Link>
        </div>
    </div>
</template>

<style scoped>
.hs {
    position: relative;
    height: calc(clamp(620px, 50vw, 780px) + 48px);
    overflow: hidden;
    font-family: Figtree, ui-sans-serif, system-ui, sans-serif;
}

@media (max-width: 767px) {
    .hs {
        height: calc(780px + 32px);
    }
}

/* Fundo claro (o mesmo do canvas de fundo): branco no topo, manchas da marca, base no tom da página. */
.hs__bg {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(30% 34% at 16% 34%, rgba(179, 136, 217, 0.4), transparent 70%),
        radial-gradient(26% 30% at 52% 72%, rgba(123, 79, 162, 0.2), transparent 70%),
        radial-gradient(28% 34% at 86% 26%, rgba(212, 184, 228, 0.6), transparent 70%),
        linear-gradient(to bottom, #ffffff 0%, #f8f4fc 50%, #faf8fc 100%);
    -webkit-mask-image: linear-gradient(to bottom, #000 86%, transparent 100%);
    mask-image: linear-gradient(to bottom, #000 86%, transparent 100%);
}

.hs__css {
    position: absolute;
    overflow: visible;
    transition: opacity 0.45s ease;
}

.hs--webgl .hs__css {
    opacity: 0;
}

.hs__shard {
    fill: rgba(255, 255, 255, 0.72);
    stroke: rgba(255, 255, 255, 0.95);
    stroke-width: 1.5;
}

.hs__shard--text {
    fill: rgba(251, 249, 255, 0.92);
}

.hs__shard--clear {
    fill: rgba(255, 255, 255, 0.18);
}

.hs__canvas {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    transition: opacity 0.45s ease;
    -webkit-mask-image: linear-gradient(to bottom, #000 86%, transparent 100%);
    mask-image: linear-gradient(to bottom, #000 86%, transparent 100%);
}

.hs--webgl .hs__canvas {
    opacity: 1;
}

.hs__layer {
    position: absolute;
    left: 0;
    top: 0;
    transform-origin: 0 0;
    pointer-events: none;
}

.hg-text {
    position: absolute;
    margin: 0;
    white-space: nowrap;
    color: transparent;
    pointer-events: auto;
    cursor: text;
    transition: color 0.45s ease;
}

.hs-eyebrow {
    font-weight: 600;
    text-transform: uppercase;
}

.hs-title {
    font-weight: 600;
}

.hs-sub {
    font-weight: 400;
}

/* Modo CSS (primeiro paint, nível baixo, movimento reduzido): o texto real aparece com as cores da marca. */
.is-css .hs-eyebrow {
    color: #632a7e;
}

.is-css .hs-title {
    color: #2a1042;
}

.is-css .hs-sub {
    color: #475569;
}

.hs-img {
    position: absolute;
    opacity: 0;
    pointer-events: none;
    object-fit: cover;
}

.hg-cta {
    position: absolute;
    display: flex;
    align-items: center;
    justify-content: center;
    color: transparent;
    font-weight: 600;
    pointer-events: auto;
    outline: none;
    white-space: nowrap;
    transition:
        color 0.45s ease,
        background-color 0.45s ease,
        box-shadow 0.2s ease;
}

.is-css .hs-cta-primary {
    background: #632a7e;
    color: #fff;
    box-shadow: 0 8px 18px -8px rgba(74, 32, 112, 0.6);
}

.is-css .hs-cta-primary:hover {
    background: #7b4fa2;
}

.is-css .hs-cta-link {
    color: #4a2070;
    font-weight: 500;
}

.is-css .hs-cta-link:hover {
    text-decoration: underline;
    text-underline-offset: 4px;
}

.is-css .hg-cta:focus-visible {
    box-shadow:
        0 0 0 3px #fff,
        0 0 0 5px #7b4fa2;
}

.hs__perf {
    position: absolute;
    left: 12px;
    bottom: 12px;
    z-index: 5;
    border-radius: 8px;
    padding: 4px 8px;
    background: rgba(42, 16, 66, 0.82);
    color: #fff;
    font: 500 11px/1.4 ui-monospace, SFMono-Regular, Menlo, monospace;
    pointer-events: none;
}

.hs__layer ::selection {
    background: rgba(123, 79, 162, 0.28);
    color: #2a1042;
}

@media (prefers-reduced-motion: reduce) {
    .hs__css,
    .hs__canvas,
    .hg-text,
    .hg-cta {
        transition: none;
    }
}
</style>
