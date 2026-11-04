/**
 * Escolha de empresa do painel NR-1 (lógica pura, testada em tests/JavaScript/nr1CompanyPicker.test.mjs): busca sem
 * acento, filtros rápidos, «Abertas recentemente» primeiro e destaque do trecho encontrado.
 */
import { DEFAULT_BRAND_HEX, contrastRatio, parseHex } from '../../utils/brandTheme.js';

export const FILTERS = [
    { key: 'todas', label: 'Todas', flag: null },
    { key: 'ativa', label: 'Com campanha ativa', flag: 'active' },
    { key: 'revisao', label: 'Em revisão', flag: 'review' },
    { key: 'publicadas', label: 'Publicadas', flag: 'published' },
    { key: 'legado', label: 'Modelo anterior', flag: 'legacy' },
    { key: 'v11', label: 'V11', flag: 'v11' },
];

const fold = (char) => char.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

export const normalize = (value) => Array.from(String(value ?? '')).map(fold).join('').trim();

/** Empresas que passam na busca (nome) e no filtro rápido. */
export function filterCompanies(companies, { term = '', filter = 'todas' } = {}) {
    const needle = normalize(term);
    const flag = FILTERS.find((item) => item.key === filter)?.flag ?? null;

    return (Array.isArray(companies) ? companies : []).filter(
        (company) => (!needle || normalize(company.name).includes(needle)) && (!flag || Boolean(company.flags?.[flag])),
    );
}

/** «Abertas recentemente» (na ordem em que foram abertas) e as demais em ordem alfabética. */
export function splitRecent(companies, recentIds) {
    const list = Array.isArray(companies) ? companies : [];
    const order = (Array.isArray(recentIds) ? recentIds : []).map(Number);
    const recent = order.map((id) => list.find((company) => Number(company.id) === id)).filter(Boolean);
    const recentSet = new Set(recent.map((company) => company.id));
    const rest = list
        .filter((company) => !recentSet.has(company.id))
        .sort((a, b) => String(a.name).localeCompare(String(b.name), 'pt-BR', { sensitivity: 'base' }));

    return { recent, rest };
}

/** Trechos do nome com o termo destacado (sem acento e sem caixa), preservando o texto original. */
export function highlightParts(name, term) {
    const text = String(name ?? '');
    const needle = normalize(term);
    if (!needle) {
        return [{ text, match: false }];
    }
    const chars = Array.from(text);
    // Cada caractere original vira 0..n caracteres normalizados; o mapa leva a posição normalizada de volta ao original.
    let folded = '';
    const owner = [];
    chars.forEach((char, index) => {
        const piece = fold(char);
        folded += piece;
        for (let i = 0; i < piece.length; i++) owner.push(index);
    });

    const parts = [];
    let cursor = 0;
    let from = folded.indexOf(needle);
    while (from !== -1) {
        const start = owner[from];
        const end = owner[from + needle.length - 1] + 1;
        if (start > cursor) parts.push({ text: chars.slice(cursor, start).join(''), match: false });
        parts.push({ text: chars.slice(start, end).join(''), match: true });
        cursor = end;
        from = folded.indexOf(needle, from + needle.length);
    }
    if (cursor < chars.length) parts.push({ text: chars.slice(cursor).join(''), match: false });

    return parts;
}

export const countLabel = (count) => (count === 1 ? '1 empresa' : `${count} empresas`);

export const campaignsLabel = (count) => (count === 1 ? '1 campanha NR-1' : `${count} campanhas NR-1`);

export function initials(name) {
    return String(name ?? '')
        .split(/\s+/)
        .filter((part) => /[\p{L}\p{N}]/u.test(part))
        .slice(0, 2)
        .map((part) => Array.from(part.replace(/^[^\p{L}\p{N}]+/u, ''))[0] ?? '')
        .join('')
        .toUpperCase();
}

/** Monograma na cor da marca da empresa (ou roxo padrão), com texto branco ou escuro conforme o contraste AA. */
export function monogramColors(hex) {
    const rgb = parseHex(hex) ?? parseHex(DEFAULT_BRAND_HEX);
    const background = `rgb(${rgb.join(' ')})`;
    const white = [255, 255, 255];
    const ink = [15, 23, 42];

    return { background, color: contrastRatio(rgb, white) >= contrastRatio(rgb, ink) ? '#ffffff' : '#0f172a' };
}
