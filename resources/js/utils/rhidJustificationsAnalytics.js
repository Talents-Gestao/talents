import { extractListItems } from '@/utils/rhidDate';

export const JUST_ANALYTICS_MAX_PAGES = 50;
export const JUST_TOP_COLLABORATORS = 10;
export const JUST_MAX_DEPT_CHART = 12;

const PALETTE = ['#632a7e', '#0d9488', '#ea580c', '#2563eb', '#db2777', '#4f46e5', '#65a30d', '#c026d3', '#0891b2', '#ca8a04'];

/**
 * @param {Record<string, unknown>} payload
 * @returns {Record<string, Record<string, unknown>>}
 */
export function buildJustificationTypeMapFromPayload(payload) {
    const items = extractListItems(payload);
    /** @type {Record<string, Record<string, unknown>>} */
    const map = {};
    for (const it of items) {
        if (!it || typeof it !== 'object') {
            continue;
        }
        const id = it.id ?? it.Id;
        if (id == null) {
            continue;
        }
        map[String(id)] = it;
    }
    return map;
}

/**
 * @param {Record<string, unknown>} payload
 * @returns {Record<string, { departmentName: string|null, idDepartment: unknown, name: string }>}
 */
export function buildPersonDepartmentMapFromPayload(payload) {
    const items = extractListItems(payload);
    /** @type {Record<string, { departmentName: string|null, idDepartment: unknown, name: string }>} */
    const map = {};
    for (const p of items) {
        if (!p || typeof p !== 'object') {
            continue;
        }
        const id = p.id;
        if (id == null) {
            continue;
        }
        map[String(id)] = {
            departmentName: p.departmentName != null && String(p.departmentName).trim() ? String(p.departmentName).trim() : null,
            idDepartment: p.idDepartment ?? null,
            name: String(p.name ?? p.nome ?? ''),
        };
    }
    return map;
}

/**
 * @param {unknown} id
 * @param {Record<string, Record<string, unknown>>|null|undefined} typeMap
 */
export function justificationTypeLabel(id, typeMap) {
    if (id == null || id === '') {
        return 'Sem tipo';
    }
    const t = typeMap?.[String(id)];
    if (!t || typeof t !== 'object') {
        return `#${id}`;
    }
    const n = t.name ?? t.nome ?? t.description ?? t.descricao;
    if (n != null && String(n).trim() !== '') {
        return String(n).trim();
    }
    return `#${id}`;
}

/**
 * @param {unknown} personId
 * @param {Record<string, { departmentName: string|null, idDepartment: unknown }>|null|undefined} personMap
 */
export function departmentLabelForJustification(personId, personMap) {
    if (personId == null || personId === '') {
        return '—';
    }
    const p = personMap?.[String(personId)];
    if (!p) {
        return 'Sem cadastro (lista ate 500)';
    }
    if (p.departmentName) {
        return p.departmentName;
    }
    if (p.idDepartment != null) {
        return `#${p.idDepartment}`;
    }
    return 'Sem departamento';
}

/**
 * Considera atestado quando o tipo ou justificativa/descrição contém "atest" (sem distinguir maiúsculas).
 *
 * @param {Record<string, unknown>} row
 * @param {Record<string, Record<string, unknown>>|null|undefined} typeMap
 */
export function isAtestadoByKeyword(row, typeMap) {
    const tid = row?.idJustificationType;
    const tl = justificationTypeLabel(tid, typeMap);
    if (/atest/i.test(tl)) {
        return true;
    }
    const j = `${row?.justificativa ?? ''} ${row?.description ?? ''}`;
    return /atest/i.test(j);
}

/**
 * @param {unknown} n
 * @returns {string}
 */
export function chartColorAt(n) {
    const i = Number(n);
    return PALETTE[Number.isFinite(i) ? Math.abs(i) % PALETTE.length : 0];
}
