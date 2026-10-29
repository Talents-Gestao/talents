import { easeOutCubic, icePiece, textPiece } from './materials.js';
import { PATTERNS } from './patterns.js';

/**
 * C — Luz. Três peças grandes e assimétricas; menos geometria, mais material. Um feixe de luz varre o vidro
 * (na entrada e, depois, raramente), as bordas têm um brilho prismático suave (lilás, azul, rosa) e o fundo
 * recebe cáusticas desenhadas por shader. Cursor e rolagem modulam o brilho de forma contida.
 */
let caustics = null;

const causticsFrag = /* glsl */ `
    uniform float uTime; uniform float uAmt; varying vec2 vUv;
    void main() {
        vec2 p = vUv * vec2(5.0, 3.2);
        float t = uTime * 0.16;
        float v = sin(p.x * 1.7 + t + sin(p.y * 1.3 - t))
                + sin(p.y * 2.1 - t * 1.3 + sin(p.x * 0.9 + t))
                + sin((p.x + p.y) * 1.2 + t * 0.7);
        float lines = pow(1.0 - abs(v) / 3.0, 10.0);
        float mask = smoothstep(0.0, 0.3, vUv.y) * smoothstep(1.0, 0.6, vUv.y) * smoothstep(0.0, 0.25, vUv.x) * smoothstep(1.0, 0.75, vUv.x);
        vec3 col = mix(vec3(0.70, 0.53, 0.85), vec3(0.62, 0.72, 1.0), vUv.x);
        gl_FragColor = vec4(col, lines * mask * uAmt);
    }
`;

export default {
    id: 'c',
    name: 'Luz',
    pattern: PATTERNS.c,
    printImage: true,
    rebuildHz: 10,
    backdropDynamic: true,
    restTilt: { x: -0.5, y: -1.6 },
    blobs: 0.8,
    contentSweep: 0.2,

    materials(ctx, i) {
        return i === 0 ? textPiece(ctx.THREE, { sweep: 0.03 }) : icePiece(ctx.THREE, { sweep: 0.5, prism: 0.5, tint: 0xfaf8ff });
    },

    setup(ctx) {
        const { THREE } = ctx;
        caustics = new THREE.Mesh(
            new THREE.PlaneGeometry(1, 1),
            new THREE.ShaderMaterial({
                uniforms: { uTime: { value: 0 }, uAmt: { value: 0 } },
                vertexShader: 'varying vec2 vUv; void main(){ vUv = uv; gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0); }',
                fragmentShader: causticsFrag,
                transparent: true,
                depthWrite: false,
                toneMapped: false,
            }),
        );
        ctx.backdrop.add(caustics);
    },

    resize(ctx) {
        if (!caustics) return;
        const { g } = ctx.state;
        // faixa sob a placa, deslocada para baixo/direita (luz de cima à esquerda): aparece na margem inferior
        const h = g.portrait ? g.ph * 0.4 : g.ph * 0.55;
        caustics.scale.set(g.pw * 1.08, h, 1);
        caustics.position.set(30 * g.Sg, g.fade / 2 - g.ph / 2 - h * 0.12, -24 * g.Sg);
    },

    entrance(ctx, p, time) {
        if (p.i === 0) return {};
        const e = easeOutCubic((time - 0.1 - p.i * 0.08) / 0.9);
        return { z: -(1 - e) * 80 * ctx.state.g.Sg, s: 0.97 + 0.03 * e, shadow: e };
    },

    update(ctx, dt, time) {
        const { g } = ctx.state;
        const scroll = ctx.pointer.scroll ?? 0;
        // feixe: passa na entrada (0,35–1,6 s) e depois a cada 12 s, em 3,6 s; a rolagem o adianta de leve
        const ph = time < 1.6 ? (time - 0.35) / 1.25 : ((time - 1.6) % 12) / 3.6;
        const span = g.W + 700 * g.Sg;
        ctx.U.uSweep.value = ph >= 0 && ph <= 1 ? -span / 2 + ph * span + scroll * 220 : -1e5;
        if (caustics) {
            caustics.material.uniforms.uTime.value = time;
            const intro = easeOutCubic((time - 0.6) / 1.6);
            // base 0,5: mais forte, as cáusticas liam como manchas sob o painel
            caustics.material.uniforms.uAmt.value = intro * (0.5 + 0.2 * (ctx.pointer.calm < 0.5 && ctx.pointer.local ? 1 : 0) + 0.25 * scroll);
        }
    },

    dispose() {
        caustics?.geometry.dispose();
        caustics?.material.dispose();
        caustics = null;
    },
};
