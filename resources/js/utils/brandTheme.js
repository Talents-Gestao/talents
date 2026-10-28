/**
 * Tema por empresa: converte a cor primária escolhida pela empresa nas variáveis CSS
 * `--primary*` (canais "R G B", ver resources/css/tokens.css), garantindo contraste WCAG AA.
 *
 * Regras:
 * - texto sobre a primária (`--on-primary`) e a própria primária ≥ 4,5:1;
 * - texto/link com a cor da marca sobre fundo branco (`--primary-text`) ≥ 4,5:1;
 * - anel de foco (`--primary-ring`) ≥ 3:1 sobre branco (componente de interface);
 * - cor inválida ou vazia → null (a interface mantém o roxo Talents padrão).
 */

export const DEFAULT_BRAND_HEX = '#632a7e';

const WHITE = [255, 255, 255];
const INK = [15, 23, 42];
const BLACK = [0, 0, 0];

const AA_TEXT = 4.5;
const AA_UI = 3;

/** @returns {number[]|null} [r, g, b] 0-255 ou null se inválido */
export function parseHex(value) {
    if (typeof value !== 'string') {
        return null;
    }

    const match = value.trim().match(/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i);

    if (!match) {
        return null;
    }

    let hex = match[1];

    if (hex.length === 3) {
        hex = hex
            .split('')
            .map((c) => c + c)
            .join('');
    }

    return [0, 2, 4].map((i) => parseInt(hex.slice(i, i + 2), 16));
}

export function relativeLuminance([r, g, b]) {
    const lin = (v) => {
        const s = v / 255;

        return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
    };

    return 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
}

export function contrastRatio(a, b) {
    const la = relativeLuminance(a);
    const lb = relativeLuminance(b);

    return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
}

/** Mistura `from` em direção a `to` (t de 0 a 1). */
function mix(from, to, t) {
    return from.map((v, i) => Math.round(v + (to[i] - v) * t));
}

/** Aproxima `color` de `target` em passos de 4% até `ratio` contra `against`. */
function nudgeUntil(color, target, against, ratio) {
    let current = color;

    for (let step = 0; step <= 25 && contrastRatio(current, against) < ratio; step += 1) {
        current = mix(color, target, (step + 1) * 0.04);
    }

    return current;
}

const channels = (rgb) => rgb.join(' ');

/**
 * @param {string|null|undefined} hex cor primária da empresa
 * @returns {{ vars: Record<string,string>, primary: number[], onPrimary: number[], adjusted: boolean }|null}
 */
export function buildBrandTheme(hex) {
    const base = parseHex(hex);

    if (!base) {
        return null;
    }

    const whiteText = contrastRatio(base, WHITE) >= contrastRatio(base, INK);
    const onPrimary = whiteText ? WHITE : INK;

    // Escurece (texto branco) ou clareia (texto escuro) a primária até o texto ficar legível.
    const primary = nudgeUntil(base, whiteText ? BLACK : WHITE, onPrimary, AA_TEXT);
    const adjusted = primary.some((v, i) => v !== base[i]);

    const primaryText = nudgeUntil(primary, BLACK, WHITE, AA_TEXT);
    const ring = nudgeUntil(primary, BLACK, WHITE, AA_UI);
    // Hover se afasta do texto, então o contraste com ele só aumenta.
    const hover = mix(primary, whiteText ? BLACK : WHITE, 0.12);

    return {
        primary,
        onPrimary,
        adjusted,
        vars: {
            '--primary': channels(primary),
            '--primary-hover': channels(hover),
            '--primary-soft': channels(mix(primary, WHITE, 0.92)),
            '--primary-text': channels(primaryText),
            '--primary-border': channels(mix(primary, WHITE, 0.6)),
            '--primary-ring': channels(ring),
            '--on-primary': channels(onPrimary),
        },
    };
}

/** Objeto de estilo para `:style` (Vue); `undefined` mantém o tema padrão. */
export function brandThemeStyle(hex) {
    return buildBrandTheme(hex)?.vars;
}
