/**
 * Parâmetros de material compartilhados pelas direções (vidro claro da marca).
 * `fx`: refract (quanto do fundo aparece pela peça), offset (deslocamento da refração),
 * sweep (feixe de luz), prism (brilho prismático nas bordas).
 */

/** Peça do texto: branco-gelo fosco, quase sem efeitos sobre a face (contraste AA garantido). */
export const textPiece = (THREE, { sweep = 0.02 } = {}) => ({
    face: {
        color: 0xfbf9ff,
        roughness: 0.32,
        specularIntensity: 0.8,
        iridescence: 0.22,
        sheen: 0.4,
        sheenRoughness: 0.5,
        sheenColor: new THREE.Color(0xb388d9),
        envMapIntensity: 0.85,
    },
    faceFx: { refract: 0.5, sweep },
    side: { color: 0xa99bc0, roughness: 0.22, specularIntensity: 1, iridescence: 0.3, envMapIntensity: 1.15 },
    sideFx: { refract: 0.3, prism: 0.06 },
});

/** Vidro claro padrão das demais peças. */
export const icePiece = (THREE, { tint = 0xf7f3fd, sweep = 0.12, prism = 0.1, refract = 0.62, offset = [0, 0] } = {}) => ({
    face: {
        color: tint,
        roughness: 0.26,
        specularIntensity: 0.85,
        iridescence: 0.3,
        sheen: 0.45,
        sheenRoughness: 0.45,
        sheenColor: new THREE.Color(0xb388d9),
        envMapIntensity: 0.9,
    },
    faceFx: { refract, sweep, offset },
    side: { color: 0xa99bc0, roughness: 0.2, specularIntensity: 1, iridescence: 0.32, envMapIntensity: 1.2 },
    sideFx: { refract: 0.3, prism, sweep: sweep * 1.4 },
});

/** Vidro transparente (direção B): pouca difusão, a refração do que está atrás domina. */
export const clearPiece = (THREE, { offset = [0, 0], prism = 0.12 } = {}) => ({
    face: {
        color: 0x1a1622,
        roughness: 0.06,
        specularIntensity: 0.7,
        iridescence: 0.18,
        envMapIntensity: 0.55,
        toneMapped: false,
    },
    faceFx: { refract: 0.93, refractK: 2.2, offset },
    side: { color: 0xc9bde0, roughness: 0.15, specularIntensity: 1, iridescence: 0.3, envMapIntensity: 1.3 },
    sideFx: { refract: 0.45, prism },
});

export const easeOutCubic = (t) => 1 - (1 - Math.min(1, Math.max(0, t))) ** 3;
