import { clearPiece, easeOutCubic, textPiece } from './materials.js';
import { PATTERNS, imageRect } from './patterns.js';

/**
 * B — Profundidade. A foto vira uma camada atrás do vidro; quatro lâminas transparentes (vidro canelado)
 * a refratam, cada uma com normal e deslocamento próprios. A peça do texto fica à frente, fosca e nítida.
 * Parallax: a foto se move ao contrário da inclinação; reflexos do ambiente giram com o cursor.
 * Entrada: as lâminas giram de perfil para a posição (como persianas abrindo) e a foto emerge do fundo.
 */
const FLUTE = [0.07, -0.05, 0.08, -0.06];
const OFFSET = [
    [0.01, 0.002],
    [-0.007, 0],
    [0.012, -0.002],
    [-0.009, 0.001],
];
let photo = null;
let tex = null;
const base = { x: 0, y: 0, z: 0, s: 1 };

export default {
    id: 'b',
    name: 'Profundidade',
    pattern: PATTERNS.b,
    printImage: false,
    backdropDynamic: true,
    restTilt: { x: -0.6, y: -1.4 },

    materials(ctx, i) {
        return i === 0 ? textPiece(ctx.THREE, { sweep: 0 }) : clearPiece(ctx.THREE, { offset: OFFSET[i - 1], prism: 0.14 });
    },

    setup(ctx) {
        const { THREE, heroImg, renderer } = ctx;
        if (!heroImg) return;
        tex = new THREE.Texture(heroImg);
        tex.colorSpace = THREE.SRGBColorSpace;
        tex.anisotropy = renderer.capabilities.getMaxAnisotropy();
        tex.needsUpdate = true;
        photo = new THREE.Mesh(
            new THREE.PlaneGeometry(1, 1),
            new THREE.MeshBasicMaterial({ map: tex, transparent: true, toneMapped: false, depthWrite: false }),
        );
        ctx.backdrop.add(photo);
    },

    resize(ctx) {
        if (!photo) return;
        const { g, layout } = ctx.state;
        const img = imageRect('b', layout, g);
        const camZ = ctx.camera.position.z;
        base.z = -90 * g.Sg;
        base.s = (camZ - base.z) / camZ; // compensa a perspectiva: mesmo tamanho aparente da composição
        base.x = (img.x + img.w / 2 - g.pw / 2) * base.s;
        base.y = (g.ph / 2 - (img.y + img.h / 2) + g.fade / 2) * base.s;
        photo.scale.set(img.w * base.s, img.h * base.s, 1);
        // recorte "cover" com foco 78% / 8% (o mesmo da Hero atual)
        const ia = tex.image.width / tex.image.height;
        const ba = img.w / img.h;
        tex.repeat.set(ba < ia ? ba / ia : 1, ba < ia ? 1 : ia / ba);
        tex.offset.set((1 - tex.repeat.x) * 0.78, (1 - tex.repeat.y) * (1 - 0.08));
    },

    pose(ctx, p) {
        const { g } = ctx.state;
        if (p.i === 0) return { z: 18 * g.Sg };
        return { ry: g.portrait ? 0 : FLUTE[p.i - 1], rx: g.portrait ? FLUTE[p.i - 1] : 0 };
    },

    liftScale: (i) => (i === 0 ? 1 : 0.6),

    entrance(ctx, p, time) {
        if (p.i === 0) return {};
        const e = easeOutCubic((time - 0.15 - (p.i - 1) * 0.08) / 0.85);
        const turn = (1 - e) * 1.25;
        return ctx.state.g.portrait ? { rx: turn, shadow: e } : { ry: turn, shadow: e };
    },

    update(ctx, dt, time) {
        if (!photo) return;
        const { g } = ctx.state;
        const { tilt, scroll } = ctx.pointer;
        const e = easeOutCubic((time - 0.05) / 0.9);
        // parallax: a foto (atrás) anda ao contrário da placa (à frente)
        photo.position.set(
            base.x - (tilt?.x ?? 0) * 26 * g.Sg,
            base.y + (tilt?.y ?? 0) * 18 * g.Sg - (scroll ?? 0) * 40,
            base.z - (1 - e) * 230 * g.Sg - (scroll ?? 0) * 120 * g.Sg,
        );
        photo.material.opacity = e;
        if (ctx.main.environmentRotation) ctx.main.environmentRotation.y = (tilt?.x ?? 0) * 0.5;
    },

    textureBytes: (ctx) => (ctx.heroImg ? ctx.heroImg.width * ctx.heroImg.height * 4 * 1.33 : 0),

    dispose() {
        photo?.geometry.dispose();
        photo?.material.dispose();
        tex?.dispose();
        photo = null;
        tex = null;
    },
};
