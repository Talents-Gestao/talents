import { easeOutCubic, icePiece, textPiece } from './materials.js';
import { PATTERNS, patternPoints } from './patterns.js';

/**
 * A — Metamorfose. As facetas da borboleta do logo (rede de nós, espelhada) formam as asas sobre a foto;
 * o corpo fica na fronteira com a peça calma do texto. Entrada: as facetas se montam a partir do corpo.
 * Repouso: asas respirando de forma quase imperceptível. Cursor: facetas inclinam como escamas.
 * Luz: um feixe percorre as facetas de tempos em tempos.
 */
const hinge = { x: 0, y: 0 };
let order = [];
// nós de vidro nos vértices internos das facetas, como a rede de nós do logo
const NODE_NAMES = ['Bd', 'V1', 'L1', 'L2'];
let nodes = [];
let nodeGeo = null;

export default {
    id: 'a',
    name: 'Metamorfose',
    pattern: PATTERNS.a,
    printImage: true,
    restTilt: { x: -0.6, y: -1 },
    contentSweep: 0.16,

    setup(ctx) {
        const { THREE } = ctx;
        nodeGeo = new THREE.SphereGeometry(1, 24, 16);
        nodes = NODE_NAMES.map((name, k) => {
            const m = new THREE.Mesh(
                nodeGeo,
                ctx.makeGlass(
                    { color: k === 0 ? 0x7b4fa2 : 0xb388d9, roughness: 0.18, specularIntensity: 1, iridescence: 0.4, envMapIntensity: 1.2 },
                    { refract: 0.2, prism: 0.2 },
                ),
            );
            ctx.block.add(m);
            return m;
        });
    },

    materials(ctx, i) {
        return i === 0 ? textPiece(ctx.THREE) : icePiece(ctx.THREE, { sweep: 0.16, prism: 0.12, tint: 0xf8f4fd });
    },

    resize(ctx) {
        const { g, keep } = ctx.state;
        const pts = patternPoints(PATTERNS.a, 0, { portrait: g.portrait, keepout: keep });
        const [bx, by] = pts.Bd;
        hinge.x = (bx - 0.5) * g.pw;
        hinge.y = (0.5 - by) * g.ph;
        nodes.forEach((m, k) => {
            const [nx, ny] = pts[NODE_NAMES[k]];
            m.userData.base = [(nx - 0.5) * g.pw, (0.5 - ny) * g.ph];
            m.userData.r = (k === 0 ? 14 : 9) * g.Sg;
        });
        // ordem de montagem: das facetas junto ao corpo para as pontas das asas
        order = ctx.pieces
            .slice(1)
            .map((p) => ({ i: p.i, d: Math.hypot(p.c.x - hinge.x, p.c.y - hinge.y) }))
            .sort((a, b) => a.d - b.d)
            .map((o) => o.i);
    },

    pose(ctx, p, time) {
        if (p.i === 0) return {};
        const { g } = ctx.state;
        const dx = p.c.x - hinge.x;
        const dy = p.c.y - hinge.y;
        const len = Math.hypot(dx, dy) || 1;
        // respiração: asas abrem/fecham ~1,2 px num ciclo lento (imperceptível como movimento, perceptível como vida)
        const breath = Math.sin((time * Math.PI * 2) / 7 - len / (600 * g.Sg)) * 1.2 * g.Sg;
        const out = { x: (dx / len) * breath, y: (dy / len) * breath, ry: (Math.sign(dx) * breath) / (900 * g.Sg) };
        // escamas: facetas próximas do cursor inclinam de leve, com o lado mais próximo afundando
        const { local, calm } = ctx.pointer;
        if (local) {
            const d = Math.hypot(local.x - p.c.x, local.y - p.c.y);
            const f = Math.exp(-((d / (300 * g.Sg)) ** 2)) * (1 - calm);
            out.ry += Math.max(-1, Math.min(1, (local.x - p.c.x) / p.half.x)) * 0.07 * f;
            out.rx = Math.max(-1, Math.min(1, (p.c.y - local.y) / p.half.y)) * 0.07 * f;
        }
        return out;
    },

    entrance(ctx, p, time) {
        if (p.i === 0) return {};
        const rank = Math.max(0, order.indexOf(p.i));
        const e = easeOutCubic((time - 0.1 - rank * 0.05) / 0.95);
        const side = p.c.y >= hinge.y ? 1 : -1;
        return {
            x: (hinge.x - p.c.x) * (1 - e),
            y: (hinge.y - p.c.y) * (1 - e),
            s: 0.12 + 0.88 * e,
            rz: (1 - e) * 0.32 * side,
            shadow: e,
        };
    },

    update(ctx, dt, time) {
        const { g } = ctx.state;
        // feixe que percorre as facetas: na entrada e depois a cada 11 s, em 3,2 s
        const period = 11;
        const ph = time < 1.6 ? (time - 0.4) / 1.6 : ((time - 1.6) % period) / 3.2;
        const span = g.W + 600 * g.Sg;
        ctx.U.uSweep.value = ph >= 0 && ph <= 1 ? -span / 2 + ph * span : -1e5;
        // nós surgem depois das facetas, do corpo para fora
        nodes.forEach((m, k) => {
            const [x, y] = m.userData.base ?? [0, 0];
            const e = easeOutCubic((time - 0.75 - k * 0.08) / 0.5);
            m.position.set(x, y, (ctx.pieces[0].frontZ ?? 8) + 2);
            m.scale.setScalar(Math.max(0.001, (m.userData.r ?? 6) * e));
        });
    },

    dispose() {
        nodeGeo?.dispose();
        nodes = [];
        nodeGeo = null;
    },
};
