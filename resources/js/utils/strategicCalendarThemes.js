/** Conteúdo fixo das campanhas mensais de saúde (independente da paleta visual). */
export const monthCampaigns = {
    1: { label: 'Janeiro Branco', campaign: 'Paz e saúde mental' },
    2: { label: 'Fevereiro Laranja', campaign: 'Leucemia e linfoma' },
    3: { label: 'Março Azul-claro', campaign: 'Hidratação e bem-estar' },
    4: { label: 'Abril Azul', campaign: 'Conscientização sobre o autismo' },
    5: { label: 'Maio Amarelo', campaign: 'Hepatites e prevenção' },
    6: { label: 'Junho Vermelho', campaign: 'Doação de sangue' },
    7: { label: 'Julho Amarelo', campaign: 'Hepatites virais' },
    8: { label: 'Agosto Dourado', campaign: 'Aleitamento materno' },
    9: { label: 'Setembro Amarelo', campaign: 'Prevenção ao suicídio' },
    10: { label: 'Outubro Rosa', campaign: 'Câncer de mama' },
    11: { label: 'Novembro Azul', campaign: 'Câncer de próstata' },
    12: { label: 'Dezembro Vermelho', campaign: 'Prevenção à AIDS' },
};

/** Paletas padrão por mês (campanhas de saúde). */
export const monthDefaultPalettes = {
    1: { color: '#475569', background: '#f1f5f9' },
    2: { color: '#ea580c', background: '#ffedd5' },
    3: { color: '#0284c7', background: '#e0f2fe' },
    4: { color: '#2563eb', background: '#dbeafe' },
    5: { color: '#ca8a04', background: '#fef9c3' },
    6: { color: '#dc2626', background: '#fee2e2' },
    7: { color: '#a16207', background: '#fef9c3' },
    8: { color: '#d97706', background: '#fef3c7' },
    9: { color: '#ca8a04', background: '#fef08a' },
    10: { color: '#db2777', background: '#fce7f3' },
    11: { color: '#1d4ed8', background: '#dbeafe' },
    12: { color: '#dc2626', background: '#fee2e2' },
};

/** Compatibilidade retroativa com imports existentes. */
export const monthThemes = Object.fromEntries(
    Object.keys(monthCampaigns).map((key) => {
        const month = Number(key);
        return [month, { ...monthCampaigns[month], ...monthDefaultPalettes[month] }];
    }),
);

export const kindThemes = {
    event: { label: 'Evento', color: '#0284c7', background: '#bae6fd' },
    ritual: { label: 'Ritual', color: '#dc2626', background: '#fecaca' },
    birthday: { label: 'Aniversário', color: '#d97706', background: '#fde68a' },
    task: { label: 'Tarefa', color: '#059669', background: '#a7f3d0' },
};

/** Contraste mínimo do acento sobre fundo claro (WCAG AA para texto grande). */
export const CALENDAR_ACCENT_MIN_CONTRAST = 3;

/** Cores rápidas — todas com contraste legível sobre fundo branco/claro. */
export const calendarAccentPresets = [
    '#475569',
    '#0284c7',
    '#2563eb',
    '#7c3aed',
    '#db2777',
    '#dc2626',
    '#ea580c',
    '#a16207',
    '#059669',
    '#0d9488',
];

/**
 * @param {string} hex
 * @returns {string | null}
 */
export function normalizeHexColor(hex) {
    const rgb = parseHexColor(hex);
    if (!rgb) {
        return null;
    }

    const toHex = (channel) => channel.toString(16).padStart(2, '0');

    return `#${toHex(rgb.r)}${toHex(rgb.g)}${toHex(rgb.b)}`;
}

/**
 * @param {string} hex
 * @returns {{ r: number, g: number, b: number } | null}
 */
function parseHexColor(hex) {
    const normalized = String(hex ?? '').trim().replace(/^#/, '');
    if (!/^[0-9a-fA-F]{6}$/.test(normalized)) {
        return null;
    }

    return {
        r: parseInt(normalized.slice(0, 2), 16),
        g: parseInt(normalized.slice(2, 4), 16),
        b: parseInt(normalized.slice(4, 6), 16),
    };
}

/**
 * @param {{ r: number, g: number, b: number }} rgb
 */
function relativeLuminance({ r, g, b }) {
    const channel = (value) => {
        const normalized = value / 255;
        return normalized <= 0.03928
            ? normalized / 12.92
            : ((normalized + 0.055) / 1.055) ** 2.4;
    };

    return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
}

/**
 * Contraste entre duas cores hex (maior valor sobre menor, padrão WCAG).
 */
export function contrastRatioBetween(hexA, hexB) {
    const rgbA = parseHexColor(hexA);
    const rgbB = parseHexColor(hexB);
    if (!rgbA || !rgbB) {
        return 1;
    }

    const luminanceA = relativeLuminance(rgbA);
    const luminanceB = relativeLuminance(rgbB);
    const lighter = Math.max(luminanceA, luminanceB);
    const darker = Math.min(luminanceA, luminanceB);

    return (lighter + 0.05) / (darker + 0.05);
}

export function contrastRatioAgainstWhite(hex) {
    return contrastRatioBetween(hex, '#ffffff');
}

export function isValidCalendarAccent(hex) {
    return contrastRatioAgainstWhite(hex) >= CALENDAR_ACCENT_MIN_CONTRAST;
}

export function calendarAccentValidationMessage() {
    return 'Escolha uma cor mais escura para manter o texto legível no calendário.';
}

/**
 * Gera fundo suave a partir do acento (mistura com branco).
 *
 * @param {string} hex
 * @param {number} [weight=0.14]
 */
export function deriveBackgroundFromAccent(hex, weight = 0.14) {
    const rgb = parseHexColor(hex);
    if (!rgb) {
        return '#f8fafc';
    }

    const mix = (channel) => Math.round(255 * (1 - weight) + channel * weight);
    const r = mix(rgb.r).toString(16).padStart(2, '0');
    const g = mix(rgb.g).toString(16).padStart(2, '0');
    const b = mix(rgb.b).toString(16).padStart(2, '0');

    return `#${r}${g}${b}`;
}

/**
 * @typedef {{ useCampaignColors?: boolean, accent?: string | null }} MonthVisualPreferences
 */

/**
 * Resolve campanha + paleta visual para um mês.
 *
 * @param {number} month
 * @param {MonthVisualPreferences} [preferences]
 */
export function resolveMonthVisual(month, preferences = {}) {
    const monthNumber = Number(month);
    const campaign = monthCampaigns[monthNumber] ?? monthCampaigns[1];
    const palette = monthDefaultPalettes[monthNumber] ?? monthDefaultPalettes[1];
    const useCampaignColors = preferences.useCampaignColors !== false;

    if (useCampaignColors) {
        return {
            ...campaign,
            color: palette.color,
            background: palette.background,
            source: 'campaign',
        };
    }

    const normalizedAccent = normalizeHexColor(preferences.accent);
    const accent = normalizedAccent && isValidCalendarAccent(normalizedAccent)
        ? normalizedAccent
        : palette.color;

    return {
        ...campaign,
        color: accent,
        background: deriveBackgroundFromAccent(accent),
        source: 'custom',
    };
}

export function monthTheme(month) {
    return resolveMonthVisual(month, { useCampaignColors: true });
}

export function kindTheme(kind) {
    return kindThemes[kind] ?? kindThemes.event;
}
