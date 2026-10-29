<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, shallowRef } from 'vue';

/**
 * Hero "Designed in glass" (WebGL). Fallback técnico: sem WebGL, com reduced-motion, aparelho fraco
 * ou contexto perdido, renderiza apenas o slot (Hero em HTML, com o mesmo conteúdo real).
 * Os textos abaixo espelham o Hero de Welcome.vue / LandingHeroTypewriter.vue.
 */

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

const FONT = 'Figtree, ui-sans-serif, system-ui, sans-serif';
// Tokens do site: tailwind.config.js (talents-*) e .app-shell (#faf8fc); subtítulo = slate-600 como na Hero atual.
const PALETTE = {
    ink: '#2a1042', // talents-900
    inkSoft: '#4a2070', // talents-700
    eyebrow: '#632a7e', // talents-600
    subtitle: '#475569', // slate-600
    primary: '#632a7e', // talents-600
    primaryHover: '#7b4fa2', // talents-500
    ring: '#7b4fa2',
    page: '#faf8fc',
};
const BG = 0xfaf8fc;
// Respiro abaixo da placa (a transição agora é claro → claro, sem degradê escuro).
const FADE_DESKTOP = 48;
const FADE_MOBILE = 32;

const supportsGlass = () => {
    if (typeof window === 'undefined') return false;
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return false;
    if ((navigator.hardwareConcurrency ?? 8) <= 2 || (navigator.deviceMemory ?? 8) <= 2) return false;
    try {
        const c = document.createElement('canvas');
        return !!(c.getContext('webgl2') || c.getContext('webgl'));
    } catch {
        return false;
    }
};

// pending: slot visível até o WebGL estar pronto · glass: vidro ativo · fallback: Hero atual
const mode = ref(supportsGlass() ? 'pending' : 'fallback');
const rootEl = ref(null);
const canvasEl = ref(null);
const layerEl = ref(null);
const layout = shallowRef(null);
const panel = ref({ w: 0, h: 0 });
const hoverCta = ref(-1);
const focusCta = ref(-1);

let engine = null;
let disposed = false;

const toFallback = () => {
    teardown();
    mode.value = 'fallback';
};

const teardown = () => {
    disposed = true;
    engine?.dispose();
    engine = null;
};

const setCta = (hover, focus) => {
    if (hover !== undefined) hoverCta.value = hover;
    if (focus !== undefined) focusCta.value = focus;
    engine?.redrawPage();
};

const onFocus = (i, e) => setCta(undefined, e.target.matches?.(':focus-visible') ? i : -1);

const layerStyle = computed(() => ({ width: `${panel.value.w}px`, height: `${panel.value.h}px` }));
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
const ctaStyle = (c) => ({ ...box(c), fontSize: `${c.size}px` });

const loadImage = (src) =>
    new Promise((resolve) => {
        const img = new Image();
        img.onload = () => (img.decode ? img.decode().catch(() => {}).then(() => resolve(img)) : resolve(img));
        img.onerror = () => resolve(null);
        img.src = src;
    });

const init = async () => {
    // Os utilitários vêm junto com o three: só são usados com o WebGL ligado e ficam fora do chunk da Welcome.
    const [THREE, { RoomEnvironment }, {
        BEVEL,
        CORNER_RADIUS,
        DEPTH,
        GAP,
        REF_WIDTH,
        applyHomography,
        clamp,
        easeOutBack,
        heroCells,
        heroLayout,
        homographyToMatrix3d,
        insetPolygon,
        keepoutRects,
        boxCenter,
        calmNear,
        roundedCorners,
        solveHomography,
        springStep,
    }] = await Promise.all([
        import('three'),
        import('three/examples/jsm/environments/RoomEnvironment.js'),
        import('@/utils/heroGlass.js'),
    ]);
    const [heroImg] = await Promise.all([
        loadImage(CONTENT.image.src),
        document.fonts?.ready,
        document.fonts?.load?.(`600 40px ${FONT}`),
        document.fonts?.load?.(`500 16px ${FONT}`),
        document.fonts?.load?.(`400 16px ${FONT}`),
    ]);
    if (disposed || !canvasEl.value || !rootEl.value) return;

    const root = rootEl.value;
    const canvas = canvasEl.value;
    const renderer = new THREE.WebGLRenderer({ canvas, antialias: true });
    renderer.setClearColor(BG, 1);
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.2;
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));

    const scene = new THREE.Scene();
    const pmrem = new THREE.PMREMGenerator(renderer);
    const roomEnv = new RoomEnvironment();
    const envTex = pmrem.fromScene(roomEnv, 0.04).texture;
    scene.environment = envTex;
    scene.environmentIntensity = 0.9;
    const key = new THREE.DirectionalLight(0xf3eeff, 1.6);
    key.position.set(-600, 800, 900);
    scene.add(key);

    const camera = new THREE.PerspectiveCamera(30, 1, 10, 6000);
    const block = new THREE.Group();
    scene.add(block);

    // Fundo claro (degradê + manchas da marca) desenhado em canvas: serve de cena e de "refração" barata.
    const bgTex = new THREE.CanvasTexture(document.createElement('canvas'));
    bgTex.colorSpace = THREE.SRGBColorSpace;
    scene.background = bgTex;

    // Halo roxo/lilás do cursor + refração falsa do fundo, injetados no shader do vidro.
    // Escolha: em vez de `transmission` (render extra da cena por frame), uma amostra do próprio
    // fundo com deslocamento pela normal — 1 fetch por fragmento, sem pass adicional.
    const glow = {
        uGlowPos: { value: new THREE.Vector3(1e5, 1e5, 0) },
        uGlowAmt: { value: 0 },
        uGlowR: { value: 300 },
        uBg: { value: bgTex },
        uRes: { value: new THREE.Vector2(1, 1) },
        uRefr: { value: 0.62 },
    };
    const makeMaterial = (params) => {
        const m = new THREE.MeshPhysicalMaterial({
            metalness: 0,
            clearcoat: 1,
            clearcoatRoughness: 0.1,
            iridescenceIOR: 1.3,
            iridescenceThicknessRange: [180, 380],
            envMap: envTex,
            ...params,
        });
        m.onBeforeCompile = (shader) => {
            Object.assign(shader.uniforms, glow);
            shader.vertexShader = shader.vertexShader
                .replace('#include <common>', '#include <common>\nvarying vec3 vGWP;')
                .replace('#include <begin_vertex>', '#include <begin_vertex>\nvGWP = (modelMatrix * vec4(transformed, 1.0)).xyz;');
            shader.fragmentShader = shader.fragmentShader
                .replace(
                    '#include <common>',
                    `#include <common>
                    varying vec3 vGWP;
                    uniform vec3 uGlowPos;
                    uniform float uGlowAmt;
                    uniform float uGlowR;
                    uniform sampler2D uBg;
                    uniform vec2 uRes;
                    uniform float uRefr;`,
                )
                .replace(
                    '#include <color_fragment>',
                    `#include <color_fragment>
                    float gd = distance(vGWP.xy, uGlowPos.xy) / uGlowR;
                    diffuseColor.rgb = mix(diffuseColor.rgb, vec3(.70, .53, .85), exp(-1.6 * gd * gd) * uGlowAmt * .26);`,
                )
                .replace(
                    '#include <emissivemap_fragment>',
                    `#include <emissivemap_fragment>
                    totalEmissiveRadiance += texture2D(uBg, gl_FragCoord.xy / uRes + normal.xy * .04).rgb * uRefr;`,
                );
        };
        m.customProgramCacheKey = () => 'hero-glass-light-v2';
        return m;
    };
    // Face: branco-gelo com irisação sutil lilás/azul/rosa. Lateral: cinza-lilás mais escuro (espessura visível).
    const makeFace = () =>
        makeMaterial({
            color: 0xfaf8ff,
            roughness: 0.3,
            specularIntensity: 0.8,
            iridescence: 0.3,
            sheen: 0.5,
            sheenRoughness: 0.5,
            sheenColor: new THREE.Color(0xb388d9),
            envMapIntensity: 0.9,
        });
    const makeSide = () =>
        makeMaterial({
            color: 0xa99bc0,
            roughness: 0.22,
            specularIntensity: 1,
            iridescence: 0.3,
            envMapIntensity: 1.15,
        });
    const shadowLayers = [
        { out: 3, alpha: 0.16 },
        { out: 12, alpha: 0.09 },
        { out: 26, alpha: 0.05 },
    ];

    const pageTex = new THREE.CanvasTexture(document.createElement('canvas'));
    pageTex.colorSpace = THREE.SRGBColorSpace;
    pageTex.anisotropy = renderer.capabilities.getMaxAnisotropy();
    const contentMat = new THREE.MeshBasicMaterial({
        map: pageTex,
        transparent: true,
        depthWrite: false,
        toneMapped: false,
        polygonOffset: true,
        polygonOffsetFactor: -2,
        polygonOffsetUnits: -2,
    });

    const pieces = Array.from({ length: 5 }, () => {
        const group = new THREE.Group();
        const glass = new THREE.Mesh(new THREE.BufferGeometry(), [makeFace(), makeSide()]);
        const content = new THREE.Mesh(new THREE.BufferGeometry(), contentMat);
        group.add(glass, content);
        // Sombra de contato/projetada: camadas suaves (roxo translúcido) atrás da peça.
        const shadows = shadowLayers.map((l) => {
            const m = new THREE.Mesh(
                new THREE.BufferGeometry(),
                new THREE.MeshBasicMaterial({ color: 0x4a2070, transparent: true, opacity: l.alpha, depthWrite: false, toneMapped: false }),
            );
            block.add(m);
            return m;
        });
        block.add(group);
        return {
            group,
            glass,
            content,
            shadows,
            c: new THREE.Vector2(),
            half: new THREE.Vector2(1, 1),
            z: { x: 0, v: 0 },
            rx: { x: 0, v: 0 },
            ry: { x: 0, v: 0 },
            hover: 0,
            dir: new THREE.Vector2(1, 0),
        };
    });

    const g = { W: 0, H: 0, pw: 0, ph: 0, S: 1, Sg: 1, portrait: false, frontZ: 0, dpr: 1, fade: 0, keep: [] };
    const measureCtx = document.createElement('canvas').getContext('2d');
    const hasLS = 'letterSpacing' in measureCtx;
    const fontOf = (size, weight) => `${weight} ${size}px ${FONT}`;
    const measure = (text, size, weight, tracking) => {
        measureCtx.font = fontOf(size, weight);
        if (hasLS) measureCtx.letterSpacing = `${tracking}px`;
        const w = measureCtx.measureText(text).width;
        return hasLS ? w : w + tracking * text.length;
    };
    const fillTracked = (ctx, text, x, y, tracking) => {
        if (hasLS) {
            ctx.letterSpacing = `${tracking}px`;
            ctx.fillText(text, x, y);
            ctx.letterSpacing = '0px';
            return;
        }
        let cx = x;
        [...text].forEach((ch) => {
            ctx.fillText(ch, cx, y);
            cx += ctx.measureText(ch).width + tracking;
        });
    };
    const baselineOf = (ctx, top, lh) => {
        const m = ctx.measureText('Hg');
        const fa = m.fontBoundingBoxAscent ?? parseFloat(ctx.font) * 0.8;
        const fd = m.fontBoundingBoxDescent ?? parseFloat(ctx.font) * 0.2;
        return top + (lh - (fa + fd)) / 2 + fa;
    };

    const drawImageCover = (ctx, img, b, fx, fy) => {
        const s = Math.max(b.w / img.width, b.h / img.height);
        const dw = img.width * s;
        const dh = img.height * s;
        ctx.drawImage(img, b.x - (dw - b.w) * fx, b.y - (dh - b.h) * fy, dw, dh);
    };

    // Imagem de página inteira do painel: é fatiada pelas células via UV.
    const drawPage = () => {
        const L = layout.value;
        if (!L) return;
        const cv = pageTex.image;
        const w = Math.round(g.pw * g.dpr);
        const h = Math.round(g.ph * g.dpr);
        if (cv.width !== w || cv.height !== h) {
            cv.width = w;
            cv.height = h;
        }
        const ctx = cv.getContext('2d');
        ctx.setTransform(g.dpr, 0, 0, g.dpr, 0, 0);
        ctx.clearRect(0, 0, g.pw, g.ph);
        ctx.textBaseline = 'alphabetic';

        if (heroImg) {
            const t = document.createElement('canvas');
            t.width = Math.round(L.image.w * g.dpr);
            t.height = Math.round(L.image.h * g.dpr);
            const tc = t.getContext('2d');
            tc.scale(g.dpr, g.dpr);
            drawImageCover(tc, heroImg, { x: 0, y: 0, w: L.image.w, h: L.image.h }, 0.78, 0.08);
            // Véu de vidro: reflexo diagonal claro sobre a foto, sem escurecer.
            tc.globalCompositeOperation = 'source-atop';
            const veil = tc.createLinearGradient(0, 0, L.image.w, L.image.h);
            veil.addColorStop(0, 'rgba(255,255,255,.20)');
            veil.addColorStop(0.45, 'rgba(255,255,255,.03)');
            veil.addColorStop(1, 'rgba(255,255,255,.10)');
            tc.fillStyle = veil;
            tc.fillRect(0, 0, L.image.w, L.image.h);
            tc.globalCompositeOperation = 'destination-in';
            const fade = g.portrait
                ? tc.createLinearGradient(0, 0, 0, L.image.h * 0.12) // curto: o rosto fica no topo da foto
                : tc.createLinearGradient(0, 0, L.image.w * 0.35, 0);
            fade.addColorStop(0, 'rgba(0,0,0,0)');
            fade.addColorStop(1, 'rgba(0,0,0,1)');
            tc.fillStyle = fade;
            tc.fillRect(0, 0, L.image.w, L.image.h);
            ctx.drawImage(t, L.image.x, L.image.y, L.image.w, L.image.h);
        }

        const e = L.eyebrow;
        ctx.font = fontOf(e.size, 600);
        ctx.fillStyle = PALETTE.eyebrow;
        e.lines.forEach((line, i) => fillTracked(ctx, line, e.x, baselineOf(ctx, e.y + i * e.lh, e.lh), e.tracking));

        const hd = L.headline;
        ctx.font = fontOf(hd.size, 600);
        const grad = ctx.createLinearGradient(0, hd.y, 0, hd.y + hd.lines.length * hd.lh);
        grad.addColorStop(0, PALETTE.ink);
        grad.addColorStop(1, PALETTE.inkSoft);
        ctx.fillStyle = grad;
        hd.lines.forEach((line, i) => fillTracked(ctx, line, hd.x, baselineOf(ctx, hd.y + i * hd.lh, hd.lh), hd.tracking));

        const sb = L.subtitle;
        ctx.font = fontOf(sb.size, 400);
        ctx.fillStyle = PALETTE.subtitle;
        sb.lines.forEach((line, i) => ctx.fillText(line, sb.x, baselineOf(ctx, sb.y + i * sb.lh, sb.lh)));

        L.ctas.forEach((c, i) => {
            const hot = hoverCta.value === i;
            const base = baselineOf(ctx, c.y, c.h);
            if (c.kind === 'primary') {
                const lift = hot ? 2 : 0;
                ctx.save();
                ctx.shadowColor = 'rgba(74,32,112,.38)';
                ctx.shadowBlur = hot ? 16 : 8;
                ctx.shadowOffsetY = hot ? 7 : 3;
                ctx.fillStyle = hot ? PALETTE.primaryHover : PALETTE.primary;
                ctx.beginPath();
                ctx.roundRect(c.x, c.y - lift, c.w, c.h, 6);
                ctx.fill();
                ctx.restore();
                ctx.font = fontOf(c.size, 600);
                ctx.fillStyle = '#ffffff';
                ctx.textAlign = 'center';
                ctx.fillText(c.label, c.x + c.w / 2, base - lift);
                ctx.textAlign = 'left';
            } else {
                ctx.font = fontOf(c.size, 500);
                ctx.fillStyle = hot ? PALETTE.ink : PALETTE.inkSoft;
                ctx.fillText(c.label, c.x, base);
                if (hot) ctx.fillRect(c.x, base + 3, c.w, 1.4);
            }
            if (focusCta.value === i) {
                ctx.strokeStyle = PALETTE.ring;
                ctx.lineWidth = 2;
                ctx.beginPath();
                ctx.roundRect(c.x - 4, c.y - 4, c.w + 8, c.h + 8, 9);
                ctx.stroke();
            }
        });
        pageTex.needsUpdate = true;
    };

    const buildPiece = (p, cellN) => {
        const pts = cellN.map(([nx, ny]) => [(nx - 0.5) * g.pw, (0.5 - ny) * g.ph]);
        const inset = insetPolygon(pts, (GAP / 2 + BEVEL) * g.Sg);
        // Pivô da inclinação = centro da peça (caixa envolvente), para não deslocar o impresso lateralmente.
        const [cx, cy] = boxCenter(inset);
        const local = inset.map(([x, y]) => [x - cx, y - cy]);
        const shapeOf = (pts2, radius) => {
            const corners = roundedCorners(pts2, radius);
            const sh = new THREE.Shape();
            sh.moveTo(...corners[0].to);
            for (let i = 1; i <= corners.length; i += 1) {
                const c = corners[i % corners.length];
                sh.lineTo(...c.from);
                sh.quadraticCurveTo(...c.ctrl, ...c.to);
            }
            return sh;
        };
        const shape = shapeOf(local, CORNER_RADIUS * g.Sg);
        const depth = DEPTH * g.Sg;
        const geo = new THREE.ExtrudeGeometry(shape, {
            depth,
            bevelEnabled: true,
            bevelThickness: BEVEL * g.Sg,
            bevelSize: BEVEL * g.Sg,
            bevelSegments: 3,
            curveSegments: 6,
        });
        geo.translate(0, 0, -depth / 2);
        const cgeo = new THREE.ShapeGeometry(shape, 6);
        const pos = cgeo.attributes.position;
        const uv = new Float32Array(pos.count * 2);
        for (let i = 0; i < pos.count; i += 1) {
            uv[i * 2] = (pos.getX(i) + cx + g.pw / 2) / g.pw;
            uv[i * 2 + 1] = (pos.getY(i) + cy + g.ph / 2) / g.ph;
        }
        cgeo.setAttribute('uv', new THREE.BufferAttribute(uv, 2));
        p.glass.geometry.dispose();
        p.content.geometry.dispose();
        p.glass.geometry = geo;
        p.content.geometry = cgeo;
        p.shadows.forEach((m, i) => {
            m.geometry.dispose();
            const o = shadowLayers[i].out * g.Sg;
            m.geometry = new THREE.ShapeGeometry(shapeOf(insetPolygon(local, -o), CORNER_RADIUS * g.Sg + o), 6);
        });
        p.content.position.z = g.frontZ + 0.5;
        p.c.set(cx, cy);
        let mx = 1;
        let my = 1;
        local.forEach(([x, y]) => {
            mx = Math.max(mx, Math.abs(x));
            my = Math.max(my, Math.abs(y));
        });
        p.half.set(mx, my);
        p.dir.set(cx, cy);
        if (p.dir.lengthSq() < 1) p.dir.set(1, 0);
        p.dir.normalize();
    };

    // Estado do ponteiro (px relativos ao Hero).
    const ptr = { px: 0, py: 0, inside: false, overUi: false };
    const canHover = window.matchMedia?.('(hover: hover) and (pointer: fine)').matches ?? true;
    const tilt = { x: 0, y: 0, rx: 0, ry: 0 };
    const sm = { glow: 0, calm: 0 };
    let hoverIdx = -1;
    let panelPtr = null; // coordenadas normalizadas do painel
    // Sobre CTA/texto (DOM interativo): âncoras do ponto de entrada, para nada "fugir" do cursor.
    let tiltAnchor = null;
    let localAnchor = null;
    let local = new THREE.Vector3();
    let visible = true;
    let raf = 0;
    let last = 0;
    let rebuildAcc = 1;
    let t0 = 0;
    let started = false;

    const raycaster = new THREE.Raycaster();
    const ndc = new THREE.Vector2();
    const inv = new THREE.Matrix4();
    const tmpV = new THREE.Vector3();

    // Degradê branco → #faf8fc (funde com o cabeçalho e com a seção seguinte) + manchas da marca desfocadas.
    const drawBackground = (W, H) => {
        const cv = bgTex.image;
        cv.width = W;
        cv.height = H;
        const ctx = cv.getContext('2d');
        const lin = ctx.createLinearGradient(0, 0, 0, H);
        lin.addColorStop(0, '#ffffff');
        lin.addColorStop(0.5, '#f8f4fc');
        lin.addColorStop(1, PALETTE.page);
        ctx.fillStyle = lin;
        ctx.fillRect(0, 0, W, H);
        const blob = (fx, fy, fr, rgb, a) => {
            const r = fr * Math.max(W, H * 1.4);
            const rg = ctx.createRadialGradient(fx * W, fy * H, 0, fx * W, fy * H, r);
            rg.addColorStop(0, `rgba(${rgb},${a})`);
            rg.addColorStop(1, `rgba(${rgb},0)`);
            ctx.fillStyle = rg;
            ctx.fillRect(0, 0, W, H);
        };
        blob(0.16, 0.34, 0.3, '179,136,217', 0.5); // talents-300
        blob(0.52, 0.72, 0.26, '123,79,162', 0.3); // talents-500
        blob(0.86, 0.26, 0.28, '212,184,228', 0.75); // talents-200
        blob(0.38, 0.5, 0.2, '99,42,126', 0.16); // talents-600
        // Bordas superior/inferior voltam ao tom exato do cabeçalho e da seção seguinte.
        const top = ctx.createLinearGradient(0, 0, 0, H * 0.14);
        top.addColorStop(0, 'rgba(255,255,255,1)');
        top.addColorStop(1, 'rgba(255,255,255,0)');
        ctx.fillStyle = top;
        ctx.fillRect(0, 0, W, H * 0.14);
        const bot = ctx.createLinearGradient(0, H * 0.78, 0, H);
        bot.addColorStop(0, 'rgba(250,248,252,0)');
        bot.addColorStop(1, 'rgba(250,248,252,1)');
        ctx.fillStyle = bot;
        ctx.fillRect(0, H * 0.78, W, H * 0.22);
        bgTex.needsUpdate = true;
    };

    const resize = () => {
        const W = root.clientWidth;
        const H = root.clientHeight;
        // sem largura útil (oculto ou em transição de viewport): mantém o último layout
        if (W < 200 || !H) return;
        g.W = W;
        g.H = H;
        g.portrait = W < 700;
        // Respiro inferior: o bloco de vidro sobe um pouco, deixando o fundo claro fundir com a seção seguinte.
        g.fade = g.portrait ? FADE_MOBILE : FADE_DESKTOP;
        const mx = g.portrait ? 16 : W * 0.055;
        const my = g.portrait ? 16 : (H - g.fade) * 0.085;
        g.pw = W - mx * 2;
        g.ph = H - g.fade - my * 2;
        block.position.y = g.fade / 2;
        g.S = g.pw / REF_WIDTH;
        g.Sg = Math.max(g.S, 0.42);
        g.frontZ = (DEPTH / 2 + BEVEL) * g.Sg;
        g.dpr = Math.min(2, Math.max(1.5, window.devicePixelRatio || 1));
        glow.uGlowR.value = 300 * g.Sg;
        renderer.setSize(W, H, false);
        renderer.getDrawingBufferSize(glow.uRes.value);
        drawBackground(W, H);
        camera.aspect = W / H;
        camera.position.set(0, 0, H / 2 / Math.tan((15 * Math.PI) / 180));
        camera.far = camera.position.z + 3000;
        camera.updateProjectionMatrix();
        panel.value = { w: g.pw, h: g.ph };
        layout.value = heroLayout({ pw: g.pw, ph: g.ph, S: g.S, portrait: g.portrait, content: CONTENT, measure });
        // Folga de 16 px (+ recuo do vidro) entre texto/CTAs e qualquer corte.
        const clearance = (16 + (GAP / 2 + BEVEL) * g.Sg) * 1.2;
        g.keep = keepoutRects(layout.value.keepout, g.pw, g.ph, clearance, g.portrait);
        drawPage();
        rebuildAcc = 1;
    };

    const updatePointer = () => {
        if (!ptr.inside) return false;
        ndc.set((ptr.px / g.W) * 2 - 1, -(ptr.py / g.H) * 2 + 1);
        return true;
    };

    const frame = (now) => {
        raf = 0;
        if (disposed || !visible || document.hidden) return;
        const t = now / 1000;
        const dt = Math.min(0.05, last ? t - last : 0.016);
        last = t;
        if (!t0) t0 = t;
        const time = t - t0;

        // Inclinação global (aponta para o cursor; no toque, deriva suave).
        let tx = 0;
        let ty = 0;
        const active = canHover && updatePointer();
        if (active) {
            tx = ndc.x;
            ty = -ndc.y;
            // Sobre CTA/texto o conjunto responde só ~30% ao movimento, a partir de onde estava ao entrar.
            if (ptr.overUi) {
                tiltAnchor ??= { x: tilt.x, y: tilt.y };
                tx = tiltAnchor.x + (tx - tiltAnchor.x) * 0.3;
                ty = tiltAnchor.y + (ty - tiltAnchor.y) * 0.3;
            } else {
                tiltAnchor = null;
            }
        } else if (!canHover) {
            tx = Math.sin(time * 0.45) * 0.5;
            ty = Math.sin(time * 0.31 + 1) * 0.35;
        }
        const k = 1 - Math.exp(-dt / 0.3);
        tilt.x += (tx - tilt.x) * k;
        tilt.y += (ty - tilt.y) * k;
        block.rotation.y = tilt.x * ((7 * Math.PI) / 180) - (1.2 * Math.PI) / 180;
        block.rotation.x = tilt.y * ((5.5 * Math.PI) / 180) - (0.8 * Math.PI) / 180;
        block.updateMatrixWorld(true);

        // Ponteiro no plano do painel (hover, brilho e padrão de corte).
        hoverIdx = -1;
        panelPtr = null;
        if (active) {
            raycaster.setFromCamera(ndc, camera);
            const hit = raycaster.intersectObjects(pieces.map((p) => p.glass), false)[0];
            inv.copy(block.matrixWorld).invert();
            const ray = raycaster.ray.clone().applyMatrix4(inv);
            if (Math.abs(ray.direction.z) > 1e-6) {
                const s = (g.frontZ - ray.origin.z) / ray.direction.z;
                local = ray.origin.clone().addScaledVector(ray.direction, s);
                panelPtr = { x: local.x / g.pw + 0.5, y: 0.5 - local.y / g.ph };
                glow.uGlowPos.value.copy(block.localToWorld(tmpV.copy(local)));
            }
            if (hit) hoverIdx = pieces.findIndex((p) => p.glass === hit.object);
        }
        sm.glow += ((panelPtr ? 1 : 0) - sm.glow) * (1 - Math.exp(-dt / 0.18));
        glow.uGlowAmt.value = sm.glow;

        // Calma: 1 sobre CTA/texto, crescendo já na aproximação dos CTAs (sem salto ao chegar).
        let calmT = 0;
        if (panelPtr && layout.value) {
            calmT = ptr.overUi ? 1 : calmNear(panelPtr.x * g.pw, panelPtr.y * g.ph, layout.value.ctas);
        }
        sm.calm += (calmT - sm.calm) * (1 - Math.exp(-dt / 0.2));
        if (ptr.overUi && panelPtr) localAnchor ??= local.clone();
        else if (!ptr.overUi) localAnchor = null;

        // Padrão de corte vivo (~45×/s).
        rebuildAcc += dt;
        if (rebuildAcc >= 1 / 45) {
            rebuildAcc = 0;
            const pp = panelPtr ? (g.portrait ? { x: panelPtr.y, y: panelPtr.x } : panelPtr) : null;
            heroCells(time, pp, g.portrait, g.keep, 1 - 0.7 * sm.calm).forEach((cell, i) => buildPiece(pieces[i], cell));
        }

        pieces.forEach((p, i) => {
            const isHover = hoverIdx === i;
            let rxT = 0;
            let ryT = 0;
            if (isHover && panelPtr) {
                // Inclinação máx. 0,24 rad; sobre CTA/texto cai para ~0,05 e usa o ponto de entrada.
                const amp = 0.24 + (0.05 - 0.24) * sm.calm;
                const lp = localAnchor ?? local;
                ryT = clamp((lp.x - p.c.x) / p.half.x, -1, 1) * amp;
                rxT = clamp((p.c.y - lp.y) / p.half.y, -1, 1) * amp;
            }
            // Elevação de 50·S; sobre CTA/texto cai para ~25%.
            springStep(p.z, isHover ? 50 * g.Sg * (1 - 0.75 * sm.calm) : 0, dt);
            springStep(p.rx, rxT, dt);
            springStep(p.ry, ryT, dt);
            p.hover += ((isHover ? 1 : 0) - p.hover) * (1 - Math.exp(-dt / 0.12));
            // Hover sutil: irisação e reflexo sobem pouco para o texto manter contraste.
            p.glass.material.forEach((m, mi) => {
                m.iridescence = 0.3 + 0.22 * p.hover;
                m.envMapIntensity = (mi === 0 ? 0.9 : 1.15) * (1 + 0.25 * p.hover);
            });

            // Entrada: peças voam de fora da tela (ease-out-back, 0.72 s, 30 ms de escalonamento).
            const e = easeOutBack((time - i * 0.03) / 0.72);
            const fly = (1 - e) * Math.max(g.W, g.H) * 0.9;
            p.group.position.set(p.c.x + p.dir.x * fly, p.c.y + p.dir.y * fly, p.z.x);
            p.group.rotation.set(p.rx.x, p.ry.x, 0);
            const lift = clamp(p.z.x / (50 * g.Sg), 0, 1.2);
            p.shadows.forEach((m, k) => {
                m.position.set(
                    p.c.x + p.dir.x * fly + (10 + 16 * lift) * g.Sg,
                    p.c.y + p.dir.y * fly - (14 + 20 * lift) * g.Sg,
                    -g.frontZ - 1,
                );
                m.material.opacity = shadowLayers[k].alpha * (1 - 0.3 * lift);
            });
        });

        // Camada de texto DOM mapeada à face da peça A (homografia): todo o texto/CTA está nela (keepout),
        // então o texto selecionável e os alvos de clique acompanham o impresso mesmo com a peça erguida.
        if (layerEl.value) {
            const hw = g.pw / 2;
            const hh = g.ph / 2;
            const pa = pieces[0];
            pa.group.updateMatrixWorld(true);
            const dst = [
                [-hw, hh],
                [hw, hh],
                [hw, -hh],
                [-hw, -hh],
            ].map(([x, y]) => {
                const v = pa.group.localToWorld(tmpV.set(x - pa.c.x, y - pa.c.y, g.frontZ + 0.5)).project(camera);
                return [(v.x * 0.5 + 0.5) * g.W, (1 - (v.y * 0.5 + 0.5)) * g.H];
            });
            const h = solveHomography(
                [
                    [0, 0],
                    [g.pw, 0],
                    [g.pw, g.ph],
                    [0, g.ph],
                ],
                dst,
            );
            layerEl.value.style.transform = homographyToMatrix3d(h);
        }

        renderer.render(scene, camera);
        if (!started) {
            started = true;
            mode.value = 'glass';
        }
        raf = requestAnimationFrame(frame);
    };

    const kick = () => {
        if (!raf && !disposed && visible && !document.hidden) {
            last = 0;
            raf = requestAnimationFrame(frame);
        }
    };

    const onMove = (e) => {
        if (e.pointerType === 'touch') return;
        const r = root.getBoundingClientRect();
        ptr.px = e.clientX - r.left;
        ptr.py = e.clientY - r.top;
        ptr.inside = true;
        ptr.overUi = !!e.target?.closest?.('.hg-cta, .hg-text');
    };
    const onLeave = () => {
        ptr.inside = false;
        ptr.overUi = false;
    };
    const onLost = (e) => {
        e.preventDefault();
        toFallback();
    };
    const onVis = () => {
        if (document.hidden) {
            cancelAnimationFrame(raf);
            raf = 0;
        } else {
            kick();
        }
    };

    const ro = new ResizeObserver(() => {
        resize();
        kick();
    });
    const io = new IntersectionObserver(([entry]) => {
        visible = entry.isIntersecting;
        if (visible) kick();
        else {
            cancelAnimationFrame(raf);
            raf = 0;
        }
    });
    root.addEventListener('pointermove', onMove);
    root.addEventListener('pointerleave', onLeave);
    canvas.addEventListener('webglcontextlost', onLost);
    document.addEventListener('visibilitychange', onVis);
    ro.observe(root);
    io.observe(root);

    engine = {
        redrawPage: drawPage,
        dispose: () => {
            cancelAnimationFrame(raf);
            ro.disconnect();
            io.disconnect();
            root.removeEventListener('pointermove', onMove);
            root.removeEventListener('pointerleave', onLeave);
            canvas.removeEventListener('webglcontextlost', onLost);
            document.removeEventListener('visibilitychange', onVis);
            pieces.forEach((p) => {
                p.glass.geometry.dispose();
                p.content.geometry.dispose();
                p.glass.material.forEach((m) => m.dispose());
                p.shadows.forEach((m) => {
                    m.geometry.dispose();
                    m.material.dispose();
                });
            });
            bgTex.dispose();
            contentMat.dispose();
            pageTex.dispose();
            envTex.dispose();
            roomEnv.traverse((o) => {
                o.geometry?.dispose();
                o.material?.dispose();
            });
            pmrem.dispose();
            renderer.dispose();
            renderer.forceContextLoss();
        },
    };

    resize();
    // Compila os shaders antes do primeiro quadro (em paralelo quando a GPU tem KHR_parallel_shader_compile),
    // para a compilação não virar uma tarefa longa na thread principal no primeiro render.
    await renderer.compileAsync?.(scene, camera).catch(() => {});
    if (disposed) return;
    await nextTick();
    kick();
};

onMounted(() => {
    if (mode.value === 'pending') {
        init().catch(() => {
            if (!disposed) toFallback();
        });
    }
});

onBeforeUnmount(teardown);
</script>

<template>
    <slot v-if="mode === 'fallback'" />
    <div v-else ref="rootEl" class="hero-glass" :class="{ 'hero-glass--on': mode === 'glass' }">
        <div class="hero-glass__fallback" :aria-hidden="mode === 'glass' || undefined" :inert="mode === 'glass' || undefined">
            <slot />
        </div>
        <canvas ref="canvasEl" class="hero-glass__canvas" aria-hidden="true" />
        <div v-if="layout" ref="layerEl" class="hero-glass__layer" :style="layerStyle">
            <p class="hg-text hg-eyebrow" :style="eyebrowStyle">
                <span v-for="(line, i) in layout.eyebrow.lines" :key="i" class="block whitespace-pre">{{ line }}{{ ' ' }}</span>
            </p>
            <h1 class="hg-text" :style="headlineStyle">
                <span v-for="(line, i) in layout.headline.lines" :key="i" class="block whitespace-pre">{{ line }}{{ ' ' }}</span>
            </h1>
            <p class="hg-text" :style="subtitleStyle">
                <span v-for="(line, i) in layout.subtitle.lines" :key="i" class="block whitespace-pre">{{ line }}{{ ' ' }}</span>
            </p>
            <img
                class="hg-image"
                :src="CONTENT.image.src"
                :alt="CONTENT.image.alt"
                :style="box(layout.image)"
                width="760"
                height="593"
            />
            <Link
                v-for="(c, i) in layout.ctas"
                :key="c.route"
                :href="route(c.route)"
                class="hg-cta"
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
.hero-glass {
    position: relative;
    height: calc(clamp(620px, 50vw, 780px) + 48px);
    overflow: hidden;
    background: transparent;
    transition: background-color 0.25s ease;
}

@media (max-width: 767px) {
    .hero-glass {
        height: calc(780px + 32px);
    }
}

/* Fundo claro: branco no topo (cabeçalho) e, na base, a cena se dissolve no fundo real da página
   (que muda com a rolagem), sem degrau de cor visível com a seção seguinte. */
.hero-glass--on {
    background: linear-gradient(to bottom, #ffffff 0%, rgba(250, 248, 252, 0) 100%);
}

.hero-glass--on .hero-glass__canvas {
    -webkit-mask-image: linear-gradient(to bottom, #000 86%, transparent 100%);
    mask-image: linear-gradient(to bottom, #000 86%, transparent 100%);
}

.hero-glass__fallback {
    position: absolute;
    inset: 0;
    overflow: hidden;
}

.hero-glass--on .hero-glass__fallback {
    visibility: hidden;
}

.hero-glass__canvas {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
}

.hero-glass--on .hero-glass__canvas {
    opacity: 1;
}

.hero-glass__layer {
    position: absolute;
    left: 0;
    top: 0;
    transform-origin: 0 0;
    pointer-events: none;
    visibility: hidden;
    font-family: Figtree, ui-sans-serif, system-ui, sans-serif;
}

.hero-glass--on .hero-glass__layer {
    visibility: visible;
}

.hg-text {
    position: absolute;
    margin: 0;
    white-space: nowrap;
    color: transparent;
    font-weight: 400;
    /* Texto selecionável (a camada em si não captura o ponteiro). */
    pointer-events: auto;
    cursor: text;
}

.hg-eyebrow {
    font-weight: 600;
    text-transform: uppercase;
}

h1.hg-text {
    font-weight: 600;
}

.hg-image {
    position: absolute;
    opacity: 0;
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
}

.hero-glass__layer ::selection {
    background: rgba(123, 79, 162, 0.28);
    color: #2a1042;
}
</style>
