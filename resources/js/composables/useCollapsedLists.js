import { ref } from 'vue';

const STORAGE_KEY = 'talents-tarefas-lists-collapsed';

const collapsedIds = ref(new Set());

function persistCollapsed() {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify([...collapsedIds.value]));
    } catch (_e) {
        // ignore quota / private mode
    }
}

function loadCollapsedFromStorage() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (raw) {
            const ids = JSON.parse(raw);
            if (Array.isArray(ids)) {
                collapsedIds.value = new Set(ids.map(Number));
            }
        }
    } catch (_e) {
        collapsedIds.value = new Set();
    }
}

let loaded = false;

function ensureLoaded() {
    if (!loaded) {
        loadCollapsedFromStorage();
        loaded = true;
    }
}

/**
 * Estado global de listas encolhidas (estilo Trello), persistido em localStorage.
 */
export function useCollapsedLists() {
    ensureLoaded();

    function isCollapsed(listId) {
        return collapsedIds.value.has(Number(listId));
    }

    function setCollapsed(listId, value) {
        const id = Number(listId);
        if (value) {
            collapsedIds.value.add(id);
        } else {
            collapsedIds.value.delete(id);
        }
        collapsedIds.value = new Set(collapsedIds.value);
        persistCollapsed();
    }

    function toggleCollapsed(listId) {
        setCollapsed(listId, !isCollapsed(listId));
    }

    return {
        collapsedIds,
        isCollapsed,
        setCollapsed,
        toggleCollapsed,
    };
}
