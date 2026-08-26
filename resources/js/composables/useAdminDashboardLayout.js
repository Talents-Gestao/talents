import { computed, ref, toValue, watch } from 'vue';

/** @typedef {'operation' | 'kpis' | 'insights'} DashboardSectionId */

/**
 * @typedef {object} AdminDashboardLayoutV2
 * @property {2 | 3} version
 * @property {DashboardSectionId[]} sectionOrder
 * @property {Record<DashboardSectionId, string[]>} sections
 */

/** v3: Leitura do mês no topo; Visão consolidada (kpis) por último. */
export const ADMIN_DASHBOARD_LAYOUT_VERSION = 3;

/** @type {DashboardSectionId[]} */
export const ADMIN_DASHBOARD_DEFAULT_SECTION_ORDER = Object.freeze([
    'insights',
    'operation',
    'kpis',
]);

/**
 * Ordem padrão dos widgets por seção (ids estáveis).
 * @type {Readonly<Record<DashboardSectionId, ReadonlyArray<string>>>}
 */
export const ADMIN_DASHBOARD_DEFAULT_SECTIONS = Object.freeze({
    operation: Object.freeze(['finance', 'tasks_today', 'calendar_today']),
    kpis: Object.freeze([
        'active_clients',
        'new_clients',
        'mrr',
        'revenue',
        'hiring',
        'hiring_days',
        'methodology',
    ]),
    insights: Object.freeze(['leads_source', 'funnel', 'monthly_goal']),
});

/** @deprecated Use ADMIN_DASHBOARD_DEFAULT_SECTIONS — alias para testes/migração. */
export const ADMIN_DASHBOARD_DEFAULT_LAYOUT = ADMIN_DASHBOARD_DEFAULT_SECTIONS;

/** @type {ReadonlyArray<string>} */
export const ADMIN_DASHBOARD_ALL_WIDGET_IDS = Object.freeze([
    ...ADMIN_DASHBOARD_DEFAULT_SECTIONS.operation,
    ...ADMIN_DASHBOARD_DEFAULT_SECTIONS.kpis,
    ...ADMIN_DASHBOARD_DEFAULT_SECTIONS.insights,
]);

/** Seção de origem de cada widget (para recolocar ids em falta). */
export const ADMIN_DASHBOARD_WIDGET_HOME = Object.freeze(
    Object.fromEntries(
        Object.entries(ADMIN_DASHBOARD_DEFAULT_SECTIONS).flatMap(([sectionId, ids]) =>
            ids.map((id) => [id, sectionId]),
        ),
    ),
);

/** Mapeamento de ids de widgets da v1 → v2. */
const V1_WIDGET_ID_MAP = Object.freeze({
    kpi_clients: 'active_clients',
    kpi_new_clients: 'new_clients',
    kpi_mrr: 'mrr',
    kpi_revenue: 'revenue',
    kpi_hiring: 'hiring',
    kpi_hiring_days: 'hiring_days',
    kpi_methodology: 'methodology',
});

/**
 * @param {string} id
 * @returns {string}
 */
function normalizeWidgetId(id) {
    return V1_WIDGET_ID_MAP[id] ?? id;
}

/**
 * @param {unknown} storedWidgets
 * @returns {string[]}
 */
function parseStoredWidgetIds(storedWidgets) {
    if (!Array.isArray(storedWidgets)) {
        return [];
    }

    return storedWidgets
        .filter((id) => typeof id === 'string')
        .map(normalizeWidgetId)
        .filter(
            (id, index, arr) =>
                ADMIN_DASHBOARD_ALL_WIDGET_IDS.includes(id) && arr.indexOf(id) === index,
        );
}

/**
 * Aceita widgets em qualquer seção (arrastar entre blocos).
 * Ids em falta voltam à seção de origem; duplicados ficam só na primeira ocorrência.
 *
 * @param {Partial<Record<DashboardSectionId, unknown>>} storedSections
 * @returns {Record<DashboardSectionId, string[]>}
 */
function mergeSectionsAllowingCrossMove(storedSections) {
    /** @type {Record<DashboardSectionId, string[]>} */
    const sections = {
        operation: [],
        kpis: [],
        insights: [],
    };
    const used = new Set();

    for (const sectionId of /** @type {DashboardSectionId[]} */ (['operation', 'kpis', 'insights'])) {
        for (const id of parseStoredWidgetIds(storedSections[sectionId])) {
            if (used.has(id)) {
                continue;
            }
            used.add(id);
            sections[sectionId].push(id);
        }
    }

    for (const id of ADMIN_DASHBOARD_ALL_WIDGET_IDS) {
        if (used.has(id)) {
            continue;
        }
        const home = /** @type {DashboardSectionId} */ (ADMIN_DASHBOARD_WIDGET_HOME[id]);
        sections[home].push(id);
    }

    return sections;
}

/**
 * @param {unknown} stored
 * @returns {AdminDashboardLayoutV2}
 */
export function createDefaultAdminDashboardLayout() {
    return {
        version: ADMIN_DASHBOARD_LAYOUT_VERSION,
        sectionOrder: [...ADMIN_DASHBOARD_DEFAULT_SECTION_ORDER],
        sections: {
            operation: [...ADMIN_DASHBOARD_DEFAULT_SECTIONS.operation],
            kpis: [...ADMIN_DASHBOARD_DEFAULT_SECTIONS.kpis],
            insights: [...ADMIN_DASHBOARD_DEFAULT_SECTIONS.insights],
        },
    };
}

/**
 * Normaliza payload v1 (flat) ou v2 e faz merge com defaults.
 * @param {unknown} stored
 * @returns {AdminDashboardLayoutV2}
 */
export function mergeAdminDashboardLayout(stored) {
    const defaults = createDefaultAdminDashboardLayout();

    if (!stored || typeof stored !== 'object') {
        return defaults;
    }

    const raw = /** @type {Record<string, unknown>} */ (stored);

    /** @type {Partial<Record<DashboardSectionId, unknown>>} */
    let storedSections = {};
    /** @type {unknown} */
    let storedOrder = null;

    if (
        (raw.version === 2 || raw.version === 3) &&
        raw.sections &&
        typeof raw.sections === 'object'
    ) {
        const sections = /** @type {Record<string, unknown>} */ (raw.sections);
        storedSections = {
            operation: sections.operation,
            kpis: sections.kpis ?? sections.indicators,
            insights: sections.insights,
        };
        // v2 → v3: aplica nova ordem padrão das seções; mantém ordem dos widgets.
        storedOrder = raw.version === 3 ? raw.sectionOrder : null;
    } else {
        // v1 flat: { operation, indicators|kpis, insights }
        storedSections = {
            operation: raw.operation,
            kpis: raw.kpis ?? raw.indicators,
            insights: raw.insights,
        };
        storedOrder = null;
    }

    /** @type {DashboardSectionId[]} */
    const sectionOrder = Array.isArray(storedOrder)
        ? storedOrder
              .filter((id) => typeof id === 'string')
              .map((id) => (id === 'indicators' ? 'kpis' : id))
              .filter(
                  (id, index, arr) =>
                      ADMIN_DASHBOARD_DEFAULT_SECTION_ORDER.includes(/** @type {DashboardSectionId} */ (id)) &&
                      arr.indexOf(id) === index,
              )
        : [];

    const missingSections = ADMIN_DASHBOARD_DEFAULT_SECTION_ORDER.filter((id) => !sectionOrder.includes(id));

    return {
        version: ADMIN_DASHBOARD_LAYOUT_VERSION,
        sectionOrder: sectionOrder.length
            ? [...sectionOrder, ...missingSections]
            : [...ADMIN_DASHBOARD_DEFAULT_SECTION_ORDER],
        sections: mergeSectionsAllowingCrossMove(storedSections),
    };
}

/**
 * @param {number|string|null|undefined} userId
 * @param {number} [version]
 */
export function adminDashboardLayoutStorageKey(userId, version = ADMIN_DASHBOARD_LAYOUT_VERSION) {
    const id = userId == null || userId === '' ? 'guest' : String(userId);

    return `talents.adminDashboard.layout.v${version}.${id}`;
}

/**
 * @param {import('vue').MaybeRefOrGetter<number|string|null|undefined>} userId
 */
export function useAdminDashboardLayout(userId) {
    const storageKey = computed(() => adminDashboardLayoutStorageKey(toValue(userId)));
    const legacyV2StorageKey = computed(() => adminDashboardLayoutStorageKey(toValue(userId), 2));
    const legacyV1StorageKey = computed(() => adminDashboardLayoutStorageKey(toValue(userId), 1));

    const readRaw = (key) => {
        if (typeof localStorage === 'undefined') {
            return null;
        }

        try {
            const raw = localStorage.getItem(key);

            return raw ? JSON.parse(raw) : null;
        } catch {
            return null;
        }
    };

    const load = () => {
        const current = readRaw(storageKey.value);
        if (current) {
            return mergeAdminDashboardLayout(current);
        }

        const legacyV2 = readRaw(legacyV2StorageKey.value);
        if (legacyV2) {
            return mergeAdminDashboardLayout(legacyV2);
        }

        const legacyV1 = readRaw(legacyV1StorageKey.value);
        if (legacyV1) {
            return mergeAdminDashboardLayout(legacyV1);
        }

        return mergeAdminDashboardLayout(null);
    };

    const layout = ref(load());

    const prefersReducedMotion = ref(false);
    if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
        const mq = window.matchMedia('(prefers-reduced-motion: reduce)');
        prefersReducedMotion.value = mq.matches;
        const onChange = (event) => {
            prefersReducedMotion.value = event.matches;
        };
        if (typeof mq.addEventListener === 'function') {
            mq.addEventListener('change', onChange);
        } else if (typeof mq.addListener === 'function') {
            mq.addListener(onChange);
        }
    }

    const dragAnimationMs = computed(() => (prefersReducedMotion.value ? 0 : 150));

    const persist = () => {
        if (typeof localStorage === 'undefined') {
            return;
        }

        try {
            localStorage.setItem(
                storageKey.value,
                JSON.stringify({
                    version: ADMIN_DASHBOARD_LAYOUT_VERSION,
                    sectionOrder: layout.value.sectionOrder,
                    sections: {
                        operation: layout.value.sections.operation,
                        kpis: layout.value.sections.kpis,
                        insights: layout.value.sections.insights,
                    },
                }),
            );
        } catch {
            // ignore quota / private mode
        }
    };

    watch(layout, persist, { deep: true });

    watch(storageKey, () => {
        layout.value = load();
    });

    const resetLayout = () => {
        layout.value = mergeAdminDashboardLayout(null);
    };

    return {
        layout,
        resetLayout,
        dragAnimationMs,
        prefersReducedMotion,
        storageKey,
    };
}
