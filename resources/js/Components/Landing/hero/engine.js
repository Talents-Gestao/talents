import {
    BEVEL,
    CORNER_RADIUS,
    DEPTH,
    GAP,
    boxCenter,
    calmNear,
    clamp,
    homographyToMatrix3d,
    insetPolygon,
    roundedCorners,
    solveHomography,
    springStep,
} from '@/utils/heroGlass.js';
import { PALETTE, drawBackdrop, drawPage } from './pageCanvas.js';
import { hexRgb, patternCells, worstContrast } from './patterns.js';
import { TIER_CONFIG, adaptQuality, percentile } from './quality.js';

/**
 * Núcleo WebGL das direções da Hero. Cada direção (scene*.js) descreve padrão, materiais, poses,
 * entrada e camadas de fundo; o núcleo cuida de renderer, câmera (1 unidade = 1 px em z = 0),
 * peças de vidro, conteúdo impresso, camada de texto (homografia), calma sobre CTAs/texto, rolagem,
 * qualidade adaptativa, pausa e liberação de recursos.
 *
 * `state` é compartilhado com o componente Vue: { g, layout, keep, hover, focus }.
 */
export function createHeroEngine({ THREE, RoomEnvironment, root, canvas, getLayer, scene, state, heroImg, tier, adapt = true, onFirstFrame, onFail }) {
    let disposed = false;
    let cfg = TIER_CONFIG[tier];
    let dpr = Math.min(window.devicePixelRatio || 1, cfg.maxDpr);

    const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, powerPreference: 'high-performance' });
    renderer.autoClear = false;
    renderer.setClearColor(0xfaf8fc, 1);
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = scene.exposure ?? 1.18;
    renderer.setPixelRatio(dpr);

    const pmrem = new THREE.PMREMGenerator(renderer);
    const roomEnv = new RoomEnvironment();
    const envTex = pmrem.fromScene(roomEnv, 0.04).texture;

    const camera = new THREE.PerspectiveCamera(30, 1, 10, 8000);

    // Fundo (renderizado na tela e num alvo usado pela refração do vidro).
    const bgCanvas = document.createElement('canvas');
    const bgTex = new THREE.CanvasTexture(bgCanvas);
    bgTex.colorSpace = THREE.SRGBColorSpace;
    const backdrop = new THREE.Scene();
    backdrop.background = bgTex;
    const rt = new THREE.WebGLRenderTarget(1, 1);
    let backdropDirty = true;

    const main = new THREE.Scene();
    main.environment = envTex;
    main.environmentIntensity = scene.envIntensity ?? 0.9;
    const key = new THREE.DirectionalLight(0xf3eeff, 1.6);
    key.position.set(-600, 800, 900);
    main.add(key);
    const block = new THREE.Group();
    main.add(block);

    // Uniformes compartilhados pelo vidro.
    const U = {
        uGlowPos: { value: new THREE.Vector3(1e5, 1e5, 0) },
        uGlowAmt: { value: 0 },
        uGlowR: { value: 300 },
        uBg: { value: rt.texture },
        uRes: { value: new THREE.Vector2(1, 1) },
        uTime: { value: 0 },
        uSweep: { value: -1e5 },
        uSweepW: { value: 140 },
    };

    const glassMaterials = [];
    const makeGlass = (params, fx = {}) => {
        const m = new THREE.MeshPhysicalMaterial({
            metalness: 0,
            clearcoat: 1,
            clearcoatRoughness: 0.1,
            iridescenceIOR: 1.3,
            iridescenceThicknessRange: [180, 380],
            envMap: envTex,
            ...params,
        });
        m.userData.fx = {
            uRefr: { value: fx.refract ?? 0.6 },
            uRefrK: { value: fx.refractK ?? 1 },
            uOffset: { value: new THREE.Vector2(...(fx.offset ?? [0, 0])) },
            uSweepAmt: { value: fx.sweep ?? 0 },
            uPrism: { value: fx.prism ?? 0 },
        };
        m.userData.baseEnv = m.envMapIntensity;
        m.userData.baseIrid = m.iridescence;
        m.onBeforeCompile = (shader) => {
            Object.assign(shader.uniforms, U, m.userData.fx);
            if (cfg.dispersion) shader.defines = { ...(shader.defines || {}), HG_DISP: '' };
            shader.vertexShader = shader.vertexShader
                .replace('#include <common>', '#include <common>\nvarying vec3 vGWP;')
                .replace('#include <begin_vertex>', '#include <begin_vertex>\nvGWP = (modelMatrix * vec4(transformed, 1.0)).xyz;');
            shader.fragmentShader = shader.fragmentShader
                .replace(
                    '#include <common>',
                    `#include <common>
                    varying vec3 vGWP;
                    uniform vec3 uGlowPos; uniform float uGlowAmt; uniform float uGlowR;
                    uniform sampler2D uBg; uniform vec2 uRes; uniform float uTime;
                    uniform float uSweep; uniform float uSweepW;
                    uniform float uRefr; uniform float uRefrK; uniform vec2 uOffset;
                    uniform float uSweepAmt; uniform float uPrism;`,
                )
                .replace(
                    '#include <color_fragment>',
                    `#include <color_fragment>
                    float hgD = distance(vGWP.xy, uGlowPos.xy) / uGlowR;
                    diffuseColor.rgb = mix(diffuseColor.rgb, vec3(.70, .53, .85), exp(-1.6 * hgD * hgD) * uGlowAmt * .24);`,
                )
                .replace(
                    '#include <emissivemap_fragment>',
                    `#include <emissivemap_fragment>
                    vec2 hgUv = gl_FragCoord.xy / uRes + normal.xy * (.04 * uRefrK) + uOffset;
                    #ifdef HG_DISP
                        vec2 hgK = normal.xy * .0035 + vec2(.0006, 0.0);
                        vec3 hgBg = vec3(texture2D(uBg, hgUv + hgK).r, texture2D(uBg, hgUv).g, texture2D(uBg, hgUv - hgK).b);
                    #else
                        vec3 hgBg = texture2D(uBg, hgUv).rgb;
                    #endif
                    totalEmissiveRadiance += hgBg * uRefr;
                    float hgBand = exp(-pow((vGWP.x + vGWP.y * .45 - uSweep) / uSweepW, 2.0));
                    totalEmissiveRadiance += vec3(1.0, .97, 1.0) * hgBand * uSweepAmt;
                    float hgFr = pow(1.0 - abs(normal.z), 2.0);
                    vec3 hgPrism = mix(vec3(.72, .62, 1.0), vec3(1.0, .72, .88), .5 + .5 * sin(vGWP.x * .018 + vGWP.y * .011 + uTime * .2));
                    totalEmissiveRadiance += hgPrism * hgFr * uPrism;`,
                );
        };
        m.customProgramCacheKey = () => `hg-dir-${cfg.dispersion ? 'd' : 'n'}`;
        glassMaterials.push(m);
        return m;
    };

    // Conteúdo impresso.
    const pageCanvas = document.createElement('canvas');
    const pageTex = new THREE.CanvasTexture(pageCanvas);
    pageTex.colorSpace = THREE.SRGBColorSpace;
    pageTex.anisotropy = renderer.capabilities.getMaxAnisotropy();
    pageTex.generateMipmaps = true;
    pageTex.minFilter = THREE.LinearMipmapLinearFilter;
    const makeContentMat = (sweepAmt) => {
        const m = new THREE.MeshBasicMaterial({
            map: pageTex,
            transparent: true,
            depthWrite: false,
            toneMapped: false,
            polygonOffset: true,
            polygonOffsetFactor: -2,
            polygonOffsetUnits: -2,
        });
        if (sweepAmt > 0) {
            // o feixe também passa pelo que está impresso (a foto), nunca pela peça do texto
            m.onBeforeCompile = (shader) => {
                Object.assign(shader.uniforms, { uSweep: U.uSweep, uSweepW: U.uSweepW, uSweepAmt: { value: sweepAmt } });
                shader.vertexShader = shader.vertexShader
                    .replace('#include <common>', '#include <common>\nvarying vec3 vGWP;')
                    .replace('#include <begin_vertex>', '#include <begin_vertex>\nvGWP = (modelMatrix * vec4(transformed, 1.0)).xyz;');
                shader.fragmentShader = shader.fragmentShader
                    .replace('#include <common>', '#include <common>\nvarying vec3 vGWP;\nuniform float uSweep; uniform float uSweepW; uniform float uSweepAmt;')
                    .replace(
                        '#include <map_fragment>',
                        `#include <map_fragment>
                        float hgB = exp(-pow((vGWP.x + vGWP.y * .45 - uSweep) / uSweepW, 2.0));
                        diffuseColor.rgb = mix(diffuseColor.rgb, vec3(1.0, .98, 1.0), hgB * uSweepAmt * diffuseColor.a);`,
                    );
            };
            m.customProgramCacheKey = () => `hg-content-sweep`;
        }
        return m;
    };
    const contentMat = makeContentMat(0);
    const contentSweepMat = makeContentMat(scene.contentSweep ?? 0);

    const ctx = {
        THREE,
        scene,
        state,
        block,
        main,
        backdrop,
        camera,
        renderer,
        U,
        makeGlass,
        heroImg,
        tier,
        pieces: [],
        pointer: { active: false, local: null, panel: null, calm: 0 },
        markBackdrop: () => {
            backdropDirty = true;
        },
    };

    const def = scene.pattern;
    const shadowLayers = () => [
        { out: 3, alpha: 0.16 },
        { out: 12, alpha: 0.09 },
        { out: 26, alpha: 0.05 },
    ].slice(0, cfg.shadowLayers);

    const makeShadows = () =>
        shadowLayers().map((l) => {
            const m = new THREE.Mesh(
                new THREE.BufferGeometry(),
                new THREE.MeshBasicMaterial({ color: 0x4a2070, transparent: true, opacity: l.alpha, depthWrite: false, toneMapped: false }),
            );
            m.userData.layer = l;
            block.add(m);
            return m;
        });

    const pieces = def.cells.map((_, i) => {
        const group = new THREE.Group();
        const mats = scene.materials(ctx, i);
        const glass = new THREE.Mesh(new THREE.BufferGeometry(), [makeGlass(mats.face, mats.faceFx), makeGlass(mats.side, mats.sideFx)]);
        const content = new THREE.Mesh(new THREE.BufferGeometry(), i === 0 ? contentMat : contentSweepMat);
        group.add(glass, content);
        block.add(group);
        return {
            i,
            group,
            glass,
            content,
            shadows: makeShadows(),
            poly: null,
            c: new THREE.Vector2(),
            half: new THREE.Vector2(1, 1),
            z: { x: 0, v: 0 },
            rx: { x: 0, v: 0 },
            ry: { x: 0, v: 0 },
            hover: 0,
            tapAt: -10,
        };
    });
    ctx.pieces = pieces;

    const shapeOf = (pts, radius) => {
        const corners = roundedCorners(pts, radius);
        const sh = new THREE.Shape();
        sh.moveTo(...corners[0].to);
        for (let i = 1; i <= corners.length; i += 1) {
            const c = corners[i % corners.length];
            sh.lineTo(...c.from);
            sh.quadraticCurveTo(...c.ctrl, ...c.to);
        }
        return sh;
    };

    const buildPiece = (p, cellN) => {
        const { g } = state;
        const pts = cellN.map(([nx, ny]) => [(nx - 0.5) * g.pw, (0.5 - ny) * g.ph]);
        const inset = insetPolygon(pts, (GAP / 2 + BEVEL) * g.Sg);
        const [cx, cy] = boxCenter(inset);
        const local = inset.map(([x, y]) => [x - cx, y - cy]);
        const radius = Math.min(CORNER_RADIUS * g.Sg, scene.cornerRadius?.(g) ?? Infinity);
        const shape = shapeOf(local, radius);
        const depth = DEPTH * g.Sg * (scene.depthScale?.(p.i) ?? 1);
        const geo = new THREE.ExtrudeGeometry(shape, {
            depth,
            bevelEnabled: true,
            bevelThickness: BEVEL * g.Sg,
            bevelSize: BEVEL * g.Sg,
            bevelSegments: tier === 'high' ? 4 : 3,
            curveSegments: 6,
        });
        geo.translate(0, 0, -depth / 2);
        const cgeo = new THREE.ShapeGeometry(shape, 6);
        const pos = cgeo.attributes.position;
        const uv = new Float32Array(pos.count * 2);
        for (let k = 0; k < pos.count; k += 1) {
            uv[k * 2] = (pos.getX(k) + cx + g.pw / 2) / g.pw;
            uv[k * 2 + 1] = (pos.getY(k) + cy + g.ph / 2) / g.ph;
        }
        cgeo.setAttribute('uv', new THREE.BufferAttribute(uv, 2));
        p.glass.geometry.dispose();
        p.content.geometry.dispose();
        p.glass.geometry = geo;
        p.content.geometry = cgeo;
        p.frontZ = depth / 2 + BEVEL * g.Sg;
        p.content.position.z = p.frontZ + 0.5;
        p.shadows.forEach((m) => {
            m.geometry.dispose();
            const o = m.userData.layer.out * g.Sg;
            m.geometry = new THREE.ShapeGeometry(shapeOf(insetPolygon(local, -o), radius + o), 6);
        });
        p.c.set(cx, cy);
        p.poly = local;
        let mx = 1;
        let my = 1;
        local.forEach(([x, y]) => {
            mx = Math.max(mx, Math.abs(x));
            my = Math.max(my, Math.abs(y));
        });
        p.half.set(mx, my);
    };

    const pieceGeomBuilt = { done: false };
    let lastPattern = null;
    const rebuild = (time, pointerPanel, pushScale) => {
        const { g } = state;
        const pp = pointerPanel ? (g.portrait ? { x: pointerPanel.y, y: pointerPanel.x } : pointerPanel) : null;
        const cells = patternCells(def, time, { portrait: g.portrait, keepout: state.keep, pointer: pp, pushScale, still: state.still });
        lastPattern = cells;
        cells.forEach((cell, i) => buildPiece(pieces[i], cell));
        pieceGeomBuilt.done = true;
    };

    const redrawPage = () => {
        if (!state.layout) return;
        drawPage(pageCanvas, {
            g: state.g,
            layout: state.layout,
            heroImg,
            printImage: scene.printImage !== false,
            hover: state.hover,
            focus: state.focus,
            dpr: Math.max(2, Math.min(3, window.devicePixelRatio || 1)),
            hasLS: state.hasLS,
        });
        pageTex.needsUpdate = true;
    };

    const resize = () => {
        const { g } = state;
        if (!g?.W) return;
        renderer.setPixelRatio(dpr);
        renderer.setSize(g.W, g.H, false);
        const buf = renderer.getDrawingBufferSize(new THREE.Vector2());
        rt.setSize(Math.max(1, Math.round(buf.x * (tier === 'high' ? 1 : 0.6))), Math.max(1, Math.round(buf.y * (tier === 'high' ? 1 : 0.6))));
        U.uRes.value.copy(buf);
        U.uGlowR.value = 300 * g.Sg;
        U.uSweepW.value = 80 * g.Sg;
        camera.aspect = g.W / g.H;
        camera.position.set(0, 0, g.H / 2 / Math.tan((15 * Math.PI) / 180));
        camera.far = camera.position.z + 4000;
        camera.updateProjectionMatrix();
        drawBackdrop(bgCanvas, g.W, g.H, { blobs: scene.blobs ?? 1 });
        bgTex.needsUpdate = true;
        backdropDirty = true;
        redrawPage();
        rebuild(0, null, 1);
        scene.resize?.(ctx);
    };

    /* ---------- ponteiro, rolagem, pausa ---------- */
    const ptr = { px: 0, py: 0, inside: false, overUi: false };
    const canHover = window.matchMedia?.('(hover: hover) and (pointer: fine)').matches ?? true;
    const tilt = { x: 0, y: 0 };
    const sm = { glow: 0, calm: 0, scroll: 0 };
    let tiltAnchor = null;
    let localAnchor = null;
    let scrollP = 0;
    let visible = true;
    let focused = true;
    let raf = 0;
    let last = 0;
    let t0 = 0;
    let started = false;
    let rebuildAcc = 1;

    const raycaster = new THREE.Raycaster();
    const ndc = new THREE.Vector2();
    const inv = new THREE.Matrix4();
    const tmpV = new THREE.Vector3();
    let homography = null;

    const frameTimes = [];
    const cpuTimes = [];
    let adaptAt = 2.5;
    const stats = { tier, dprHistory: [dpr], adaptations: [] };

    /* ---------- passo de simulação ---------- */
    const step = (time, dt, override = null) => {
        const { g } = state;
        let px = ptr.px;
        let py = ptr.py;
        let inside = ptr.inside;
        let overUi = ptr.overUi;
        if (override) {
            ({ px, py } = override);
            inside = !!override.inside;
            overUi = !!override.overUi;
        }
        const active = (canHover || override) && inside;

        let tx = 0;
        let ty = 0;
        if (active) {
            ndc.set((px / g.W) * 2 - 1, -(py / g.H) * 2 + 1);
            tx = ndc.x;
            ty = -ndc.y;
            if (overUi) {
                tiltAnchor ??= { x: tilt.x, y: tilt.y };
                tx = tiltAnchor.x + (tx - tiltAnchor.x) * 0.3;
                ty = tiltAnchor.y + (ty - tiltAnchor.y) * 0.3;
            } else {
                tiltAnchor = null;
            }
        } else if (!canHover && !override) {
            // toque: deriva automática suave (sem cursor)
            tx = Math.sin(time * 0.33) * 0.42;
            ty = Math.sin(time * 0.23 + 1) * 0.3;
        }
        const k = 1 - Math.exp(-dt / 0.3);
        tilt.x += (tx - tilt.x) * k;
        tilt.y += (ty - tilt.y) * k;
        const rest = scene.restTilt ?? { x: -0.8, y: -1.2 };
        const tiltScale = scene.tiltScale ?? 1;
        block.rotation.y = (tilt.x * 7 * tiltScale + rest.y) * (Math.PI / 180);
        block.rotation.x = (tilt.y * 5.5 * tiltScale + rest.x) * (Math.PI / 180);

        // rolagem: as peças recuam em profundidade e o bloco sobe mais devagar (parallax), sem prender a página
        sm.scroll += (scrollP - sm.scroll) * (1 - Math.exp(-dt / 0.12));
        block.position.set(0, g.fade / 2 - sm.scroll * 70, -sm.scroll * 180 * g.Sg);
        block.updateMatrixWorld(true);

        let hoverIdx = -1;
        let panel = null;
        let local = null;
        if (active) {
            raycaster.setFromCamera(ndc, camera);
            const hit = raycaster.intersectObjects(pieces.map((p) => p.glass), false)[0];
            inv.copy(block.matrixWorld).invert();
            const ray = raycaster.ray.clone().applyMatrix4(inv);
            if (Math.abs(ray.direction.z) > 1e-6) {
                const s = ((pieces[0].frontZ ?? 8) - ray.origin.z) / ray.direction.z;
                local = ray.origin.clone().addScaledVector(ray.direction, s);
                panel = { x: local.x / g.pw + 0.5, y: 0.5 - local.y / g.ph };
                U.uGlowPos.value.copy(block.localToWorld(tmpV.copy(local)));
            }
            if (hit) hoverIdx = pieces.findIndex((p) => p.glass === hit.object);
        }
        sm.glow += ((panel ? 1 : 0) - sm.glow) * (1 - Math.exp(-dt / 0.18));
        U.uGlowAmt.value = sm.glow * (scene.glow ?? 1);

        let calmT = 0;
        if (panel && state.layout) calmT = overUi ? 1 : calmNear(panel.x * g.pw, panel.y * g.ph, state.layout.ctas);
        sm.calm += (calmT - sm.calm) * (1 - Math.exp(-dt / 0.2));
        if (overUi && local) localAnchor ??= local.clone();
        else if (!overUi) localAnchor = null;

        ctx.pointer = { active, local, panel, calm: sm.calm, hoverIdx, tilt, scroll: sm.scroll };

        if (def.rebuildHz || scene.rebuildHz) {
            rebuildAcc += dt;
            if (rebuildAcc >= 1 / (scene.rebuildHz ?? 24)) {
                rebuildAcc = 0;
                rebuild(time, panel, 1 - 0.7 * sm.calm);
            }
        }

        pieces.forEach((p, i) => {
            const isHover = hoverIdx === i;
            let rxT = 0;
            let ryT = 0;
            if (isHover && local) {
                const amp = 0.24 + (0.05 - 0.24) * sm.calm;
                const lp = localAnchor ?? local;
                ryT = clamp((lp.x - p.c.x) / p.half.x, -1, 1) * amp * (scene.hoverTilt?.(i) ?? 1);
                rxT = clamp((p.c.y - lp.y) / p.half.y, -1, 1) * amp * (scene.hoverTilt?.(i) ?? 1);
            }
            const tapLift = Math.max(0, 1 - (time - p.tapAt) / 0.7);
            const liftT = (isHover ? 50 * g.Sg * (1 - 0.75 * sm.calm) : 0) + tapLift * 30 * g.Sg;
            springStep(p.z, liftT * (scene.liftScale?.(i) ?? 1), dt);
            springStep(p.rx, rxT, dt);
            springStep(p.ry, ryT, dt);
            p.hover += ((isHover ? 1 : 0) - p.hover) * (1 - Math.exp(-dt / 0.12));
            p.glass.material.forEach((m) => {
                m.iridescence = m.userData.baseIrid + 0.18 * p.hover;
                m.envMapIntensity = m.userData.baseEnv * (1 + 0.22 * p.hover);
            });

            const pose = scene.pose?.(ctx, p, time) ?? {};
            const ent = scene.entrance?.(ctx, p, time) ?? {};
            const sc = (pose.s ?? 1) * (ent.s ?? 1);
            p.group.position.set(
                p.c.x + (pose.x ?? 0) + (ent.x ?? 0),
                p.c.y + (pose.y ?? 0) + (ent.y ?? 0),
                p.z.x + (pose.z ?? 0) + (ent.z ?? 0),
            );
            p.group.rotation.set(
                p.rx.x + (pose.rx ?? 0) + (ent.rx ?? 0),
                p.ry.x + (pose.ry ?? 0) + (ent.ry ?? 0),
                (pose.rz ?? 0) + (ent.rz ?? 0),
            );
            p.group.scale.setScalar(Math.max(0.001, sc));
            const lift = clamp(p.z.x / (50 * g.Sg), 0, 1.2);
            p.shadows.forEach((m) => {
                m.position.set(
                    p.group.position.x + (10 + 16 * lift) * g.Sg,
                    p.group.position.y - (14 + 20 * lift) * g.Sg,
                    -(p.frontZ ?? 8) - 1,
                );
                m.scale.setScalar(Math.max(0.001, sc));
                m.material.opacity = m.userData.layer.alpha * (1 - 0.3 * lift) * (ent.shadow ?? 1);
            });
        });

        U.uTime.value = time;
        scene.update?.(ctx, dt, time);

        // camada DOM sobre a face da peça do texto
        const layer = getLayer();
        const pa = pieces[0];
        pa.group.updateMatrixWorld(true);
        const hw = g.pw / 2;
        const hh = g.ph / 2;
        const dst = [
            [-hw, hh],
            [hw, hh],
            [hw, -hh],
            [-hw, -hh],
        ].map(([x, y]) => {
            const v = pa.group.localToWorld(tmpV.set(x - pa.c.x, y - pa.c.y, (pa.frontZ ?? 8) + 0.5)).project(camera);
            return [(v.x * 0.5 + 0.5) * g.W, (1 - (v.y * 0.5 + 0.5)) * g.H];
        });
        homography = solveHomography(
            [
                [0, 0],
                [g.pw, 0],
                [g.pw, g.ph],
                [0, g.ph],
            ],
            dst,
        );
        if (layer && !override) layer.style.transform = homographyToMatrix3d(homography);
    };

    const render = (target = null) => {
        if (backdropDirty || scene.backdropDynamic) {
            renderer.setRenderTarget(rt);
            renderer.clear();
            renderer.render(backdrop, camera);
            backdropDirty = false;
        }
        renderer.setRenderTarget(target);
        renderer.clear();
        renderer.render(backdrop, camera);
        renderer.clearDepth();
        renderer.render(main, camera);
        renderer.setRenderTarget(null);
    };

    const frame = (now) => {
        raf = 0;
        if (disposed || !visible || !focused || document.hidden) return;
        const c0 = performance.now();
        const t = now / 1000;
        const dt = Math.min(0.05, last ? t - last : 0.016);
        if (last) frameTimes.push((t - last) * 1000);
        last = t;
        if (!t0) t0 = t;
        const time = t - t0;
        step(time, dt);
        render();
        cpuTimes.push(performance.now() - c0);
        if (frameTimes.length > 240) frameTimes.shift();
        if (cpuTimes.length > 240) cpuTimes.shift();

        // qualidade adaptativa: a cada janela, compara o p95 com o orçamento do nível
        if (adapt && time > adaptAt && frameTimes.length > 40) {
            const p95 = percentile(frameTimes, 0.95);
            const next = adaptQuality({ tier, dpr, p95 });
            stats.adaptations.push({ at: +time.toFixed(1), p95: +p95.toFixed(1), ...next });
            adaptAt = time + 3;
            if (next.changed) {
                frameTimes.length = 0;
                if (next.tier === 'low') {
                    onFail?.('slow');
                    return;
                }
                if (next.tier !== tier) {
                    tier = next.tier;
                    cfg = TIER_CONFIG[tier];
                    glassMaterials.forEach((m) => {
                        m.needsUpdate = true;
                    });
                }
                dpr = next.dpr;
                stats.dprHistory.push(dpr);
                resize();
            }
        }

        if (!started) {
            started = true;
            onFirstFrame?.();
        }
        raf = requestAnimationFrame(frame);
    };

    const kick = () => {
        if (!raf && !disposed && visible && focused && !document.hidden) {
            last = 0;
            raf = requestAnimationFrame(frame);
        }
    };
    const stop = () => {
        cancelAnimationFrame(raf);
        raf = 0;
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
    // toque: um toque numa peça (fora de CTA/texto) a ergue por um instante
    const onDown = (e) => {
        if (e.pointerType !== 'touch' || e.target?.closest?.('.hg-cta, .hg-text')) return;
        const r = root.getBoundingClientRect();
        ndc.set(((e.clientX - r.left) / state.g.W) * 2 - 1, -((e.clientY - r.top) / state.g.H) * 2 + 1);
        raycaster.setFromCamera(ndc, camera);
        const hit = raycaster.intersectObjects(pieces.map((p) => p.glass), false)[0];
        const p = hit && pieces.find((q) => q.glass === hit.object);
        if (p && p.i !== 0) p.tapAt = (performance.now() / 1000) - t0;
    };
    const onScroll = () => {
        const r = root.getBoundingClientRect();
        scrollP = clamp(-r.top / Math.max(1, r.height), 0, 1);
        kick();
    };
    const onVis = () => (document.hidden ? stop() : kick());
    const onBlur = () => {
        focused = false;
        stop();
    };
    const onFocus = () => {
        focused = true;
        kick();
    };
    const onLost = (e) => {
        e.preventDefault();
        onFail?.('context-lost');
    };

    const io = new IntersectionObserver(([entry]) => {
        visible = entry.isIntersecting;
        if (visible) kick();
        else stop();
    });
    root.addEventListener('pointermove', onMove);
    root.addEventListener('pointerleave', onLeave);
    root.addEventListener('pointerdown', onDown);
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('blur', onBlur);
    window.addEventListener('focus', onFocus);
    document.addEventListener('visibilitychange', onVis);
    canvas.addEventListener('webglcontextlost', onLost);
    io.observe(root);

    scene.setup?.(ctx);

    /* ---------- laboratório (só usado com ?hero= em ambiente local) ---------- */
    const sampleContrast = (cases) => {
        const { g, layout } = state;
        const W = Math.round(g.W);
        const H = Math.round(g.H);
        const gl = renderer.getContext();
        const prevDpr = dpr;
        const prevRes = U.uRes.value.clone();
        renderer.setPixelRatio(1);
        renderer.setSize(W, H, false);
        U.uRes.value.set(W, H);
        stop();
        const boxes = [
            { id: 'eyebrow', inks: [PALETTE.eyebrow], b: { x: layout.eyebrow.x, y: layout.eyebrow.y, w: layout.keepout[0].w, h: layout.keepout[0].h } },
            { id: 'título', inks: [PALETTE.ink, PALETTE.inkSoft], b: layout.keepout[1] },
            { id: 'subtítulo', inks: [PALETTE.subtitle], b: layout.keepout[2] },
            ...layout.ctas
                .map((c, i) => ({ c, i }))
                .filter(({ c }) => c.kind !== 'primary')
                .map(({ c }) => ({ id: `CTA ${c.label}`, inks: [PALETTE.inkSoft, PALETTE.ink], b: c })),
        ];
        const out = {};
        const buf = new Uint8Array(W * H * 4);
        cases.forEach((cs) => {
            for (let f = 0; f < 50; f += 1) step(cs.t + f / 60, 1 / 60, cs.pointer ? { px: cs.pointer[0], py: cs.pointer[1], inside: true, overUi: !!cs.overUi } : { px: 0, py: 0, inside: false });
            pieces.forEach((p) => {
                p.content.visible = false;
            });
            // o three só aplica tone mapping na saída para a tela: lê o próprio canvas logo após renderizar
            render(null);
            gl.readPixels(0, 0, W, H, gl.RGBA, gl.UNSIGNED_BYTE, buf);
            pieces.forEach((p) => {
                p.content.visible = true;
            });
            boxes.forEach(({ id, inks, b }) => {
                const corners = [
                    [b.x, b.y],
                    [b.x + b.w, b.y],
                    [b.x + b.w, b.y + b.h],
                    [b.x, b.y + b.h],
                ].map((q) => {
                    const w = homography[6] * q[0] + homography[7] * q[1] + 1;
                    return [(homography[0] * q[0] + homography[1] * q[1] + homography[2]) / w, (homography[3] * q[0] + homography[4] * q[1] + homography[5]) / w];
                });
                const x0 = Math.max(0, Math.floor(Math.min(...corners.map((q) => q[0]))));
                const x1 = Math.min(W - 1, Math.ceil(Math.max(...corners.map((q) => q[0]))));
                const y0 = Math.max(0, Math.floor(Math.min(...corners.map((q) => q[1]))));
                const y1 = Math.min(H - 1, Math.ceil(Math.max(...corners.map((q) => q[1]))));
                const samples = [];
                for (let y = y0; y <= y1; y += 2) {
                    for (let x = x0; x <= x1; x += 2) {
                        const o = ((H - 1 - y) * W + x) * 4;
                        samples.push([buf[o], buf[o + 1], buf[o + 2]]);
                    }
                }
                inks.forEach((ink) => {
                    const r = worstContrast(hexRgb(ink), samples);
                    if (!out[id] || r.ratio < out[id].ratio) out[id] = { ratio: +r.ratio.toFixed(2), bg: r.bg, ink, case: cs.label };
                });
            });
        });
        renderer.setPixelRatio(prevDpr);
        renderer.setSize(g.W, g.H, false);
        U.uRes.value.copy(prevRes);
        kick();
        return out;
    };

    // captura determinística (vídeos): congela o laço e avança a simulação em passos fixos
    let capT = 0;
    const lab = {
        beginCapture: () => {
            stop();
            capT = 0;
        },
        renderAt: (t, pointer = null) => {
            while (capT < t) {
                capT = Math.min(t, capT + 1 / 60);
                step(capT, 1 / 60, pointer ? { px: pointer[0], py: pointer[1], inside: true, overUi: !!pointer[2] } : { px: 0, py: 0, inside: false });
            }
            const layer = getLayer();
            if (layer && homography) layer.style.transform = homographyToMatrix3d(homography);
            render();
        },
        endCapture: () => kick(),
        stats: () => ({
            tier,
            dpr,
            frame_p50: +percentile(frameTimes, 0.5).toFixed(1),
            frame_p95: +percentile(frameTimes, 0.95).toFixed(1),
            cpu_p50: +percentile(cpuTimes, 0.5).toFixed(2),
            cpu_p95: +percentile(cpuTimes, 0.95).toFixed(2),
            adaptations: stats.adaptations,
            textureMB: +(
                ((pageCanvas.width * pageCanvas.height * 4 * 1.33) +
                    (bgCanvas.width * bgCanvas.height * 4) +
                    (rt.width * rt.height * 4) +
                    (scene.textureBytes?.(ctx) ?? 0) +
                    6 * 256 * 256 * 8 * 1.33) /
                1048576
            ).toFixed(1),
            pieces: pieces.length,
        }),
        sampleContrast,
    };

    resize();

    // compila os shaders antes do primeiro quadro (em paralelo quando a GPU tem KHR_parallel_shader_compile),
    // para não gerar tarefas longas na thread principal durante a entrada
    const ready = Promise.all([renderer.compileAsync(main, camera), renderer.compileAsync(backdrop, camera)]).catch(() => {});

    return {
        ready,
        resize,
        redrawPage,
        kick,
        lab,
        dispose: () => {
            disposed = true;
            stop();
            io.disconnect();
            root.removeEventListener('pointermove', onMove);
            root.removeEventListener('pointerleave', onLeave);
            root.removeEventListener('pointerdown', onDown);
            window.removeEventListener('scroll', onScroll);
            window.removeEventListener('blur', onBlur);
            window.removeEventListener('focus', onFocus);
            document.removeEventListener('visibilitychange', onVis);
            canvas.removeEventListener('webglcontextlost', onLost);
            scene.dispose?.(ctx);
            pieces.forEach((p) => {
                p.glass.geometry.dispose();
                p.content.geometry.dispose();
                p.shadows.forEach((m) => {
                    m.geometry.dispose();
                    m.material.dispose();
                });
            });
            glassMaterials.forEach((m) => m.dispose());
            contentMat.dispose();
            contentSweepMat.dispose();
            pageTex.dispose();
            bgTex.dispose();
            rt.dispose();
            envTex.dispose();
            roomEnv.traverse((o) => {
                o.geometry?.dispose();
                o.material?.dispose();
            });
            pmrem.dispose();
            renderer.dispose();
            renderer.forceContextLoss();
        },
        get lastPattern() {
            return lastPattern;
        },
        get built() {
            return pieceGeomBuilt.done;
        },
    };
}
