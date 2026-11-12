<script setup>
import BoardSection from '@/Components/Tasks/BoardSection.vue';
import CardModal from '@/Components/Tasks/CardModal.vue';
import TaskBoardsNav from '@/Components/Tasks/TaskBoardsNav.vue';
import { boardSections, chunkIds, isBoardExpanded } from '@/utils/taskBoards';
import { router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, shallowReactive, watch } from 'vue';

/**
 * Tela única de Tarefas (Admin e portal): todos os quadros visíveis empilhados, cada um recolhível.
 * O servidor manda a lista com o resumo de cada quadro e o conteúdo só do último usado (`boardPayload`);
 * o conteúdo dos demais é pedido ao expandir, por recarga parcial (`boardPayloads`, cabeçalho X-Task-Boards).
 */
const props = defineProps({
    isAdmin: { type: Boolean, default: false },
    /** Admin com tarefas:edit (ordenar quadros). */
    canEdit: { type: Boolean, default: false },
    boards: { type: Array, default: () => [] },
    /** Conteúdo do quadro inicial (último usado, ou o da rota /{board}). */
    boardPayload: { type: Object, default: null },
    focusBoardId: { type: Number, default: null },
    /** Rota /{board}: expande e rola até o quadro. */
    scrollToFocus: { type: Boolean, default: false },
    companyUsers: { type: Array, default: () => [] },
    teamUsers: { type: Array, default: () => [] },
    companies: { type: Array, default: () => [] },
    visibilityCardOptions: { type: Array, default: () => [] },
    recurrenceOptions: { type: Array, default: () => [] },
    showRouteName: { type: String, required: true },
    emptyMessage: { type: String, default: 'Nenhum quadro disponível.' },
});

const BOARDS_HEADER = 'X-Task-Boards';
const ARCHIVED_HEADER = 'X-Task-Boards-Archived';
const OPENED_HEADER = 'X-Task-Board-Opened';
const BATCH_SIZE = 6;

/* Preferências por usuário (expandido/recolhido e Kanban/Lista de cada quadro). */
const page = usePage();
const STORAGE_KEY = `talents-tarefas-quadros:${props.isAdmin ? 'admin' : 'client'}:${page.props.auth?.user?.id ?? 'anon'}`;
const LEGACY_VIEW_KEY = props.isAdmin ? 'talents-tarefas-view-mode' : 'talents-client-tarefas-view-mode';

function readPrefs() {
    try {
        const parsed = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
        return {
            expanded: parsed?.expanded && typeof parsed.expanded === 'object' ? parsed.expanded : {},
            view: parsed?.view && typeof parsed.view === 'object' ? parsed.view : {},
        };
    } catch (_e) {
        return { expanded: {}, view: {} };
    }
}

function readDefaultView() {
    try {
        return localStorage.getItem(LEGACY_VIEW_KEY) === 'lista' ? 'lista' : 'kanban';
    } catch (_e) {
        return 'kanban';
    }
}

const prefs = reactive(readPrefs());
const defaultView = readDefaultView();

function savePrefs() {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs));
    } catch (_e) {
        // modo privado / cota: segue sem lembrar
    }
}

const isExpanded = (board) => isBoardExpanded(board, prefs.expanded, props.focusBoardId);
const viewModeOf = (id) => (prefs.view[id] === 'lista' ? 'lista' : prefs.view[id] === 'kanban' ? 'kanban' : defaultView);

function setViewMode(id, mode) {
    prefs.view[id] = mode;
    savePrefs();
}

/* Conteúdo carregado por quadro (id → payload), estado de carga e quadros com arquivados à mostra. */
const payloads = shallowReactive({});
const loading = reactive({});
const failed = reactive({});
const archived = reactive(new Set(props.boardPayload?.show_archived ? [props.boardPayload.id] : []));

watch(
    () => props.boardPayload,
    (payload) => {
        if (payload?.id && Boolean(payload.show_archived) === archived.has(payload.id)) {
            payloads[payload.id] = payload;
        }
    },
    { immediate: true },
);

function load(ids, { withBoards = false, opened = null } = {}) {
    const list = ids.map(Number).filter((id) => Number.isFinite(id));
    if (!list.length) {
        return;
    }
    list.forEach((id) => {
        loading[id] = true;
        delete failed[id];
    });

    const headers = { [BOARDS_HEADER]: list.join(',') };
    const withArchived = list.filter((id) => archived.has(id));
    if (withArchived.length) {
        headers[ARCHIVED_HEADER] = withArchived.join(',');
    }
    if (opened) {
        headers[OPENED_HEADER] = String(opened);
    }

    let received = [];
    router.reload({
        only: withBoards ? ['boardPayloads', 'boards'] : ['boardPayloads'],
        headers,
        onSuccess: (response) => {
            received = response.props.boardPayloads || [];
            received.forEach((payload) => {
                payloads[payload.id] = payload;
            });
        },
        onFinish: () => {
            const got = new Set(received.map((payload) => Number(payload.id)));
            list.forEach((id) => {
                delete loading[id];
                if (!got.has(id) && !payloads[id]) {
                    failed[id] = true;
                }
            });
        },
    });
}

/* Pedidos das seções que ficaram visíveis e expandidas, juntos em lotes. */
const queued = new Set();
let flushTimer = null;

function requestPayload(id) {
    if (loading[id] || queued.has(id)) {
        return;
    }
    queued.add(Number(id));
    clearTimeout(flushTimer);
    flushTimer = setTimeout(() => {
        const ids = [...queued].filter((boardId) => !loading[boardId]);
        queued.clear();
        chunkIds(ids, BATCH_SIZE).forEach((chunk) => load(chunk));
    }, 40);
}

onBeforeUnmount(() => clearTimeout(flushTimer));

/** Recarrega um quadro (depois de editar) e a lista de quadros (resumo, nome, cor). */
function refresh(id) {
    load([id], { withBoards: true });
}

function toggle(id) {
    const board = props.boards.find((b) => Number(b.id) === Number(id));
    if (!board) return;
    const open = !isExpanded(board);
    prefs.expanded[id] = open;
    savePrefs();
    if (open && !loading[id]) {
        if (payloads[id]) {
            return;
        }
        load([id], { opened: id });
    }
}

function toggleArchived(id) {
    if (archived.has(id)) {
        archived.delete(id);
    } else {
        archived.add(id);
    }
    load([id]);
}

/* Busca, seções e navegação. */
const search = ref('');
const sections = computed(() => boardSections(props.boards, search.value));
const visibleBoards = computed(() => [...sections.value.favorites, ...sections.value.internal, ...sections.value.company]);

const groupLabels = computed(() =>
    props.isAdmin
        ? { favorites: 'Favoritos', internal: 'Quadros internos', company: 'Quadros por empresa' }
        : { favorites: 'Favoritos', internal: 'Quadros da Talents', company: 'Quadros da empresa' },
);

const groups = computed(() =>
    ['favorites', 'internal', 'company']
        .map((key) => ({ key, label: groupLabels.value[key], boards: sections.value[key] }))
        .filter((group) => group.boards.length),
);

function setAll(open) {
    visibleBoards.value.forEach((board) => {
        prefs.expanded[board.id] = open;
    });
    savePrefs();
}

const prefersReducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

function scrollToBoard(id, { focus = true } = {}) {
    nextTick(() => {
        const el = document.getElementById(`quadro-${id}`);
        if (!el) return;
        el.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'start' });
        if (focus) {
            el.querySelector('[data-board-toggle]')?.focus({ preventScroll: true });
        }
    });
}

function jump(id) {
    const board = props.boards.find((b) => Number(b.id) === Number(id));
    if (board && !isExpanded(board)) {
        toggle(id);
    }
    scrollToBoard(id);
}

onMounted(() => {
    if (props.scrollToFocus && props.focusBoardId) {
        prefs.expanded[props.focusBoardId] = true;
        savePrefs();
        scrollToBoard(props.focusBoardId, { focus: false });
    }
});

/* Modal do cartão (um para a tela toda, com o quadro do cartão aberto). */
const modalOpen = ref(false);
const selectedCard = ref(null);
const selectedBoardId = ref(null);
const modalPayload = computed(() => (selectedBoardId.value ? payloads[selectedBoardId.value] ?? null : null));

function openCard(boardId, card) {
    selectedBoardId.value = boardId;
    selectedCard.value = card;
    modalOpen.value = true;
}

function syncSelectedCard(cardId) {
    const freshCard = (modalPayload.value?.lists || [])
        .flatMap((list) => list.cards || [])
        .find((card) => Number(card.id) === Number(cardId));

    if (freshCard) {
        selectedCard.value = freshCard;
    }
}

/* O quadro do cartão aberto foi recarregado (etapa, comentário, anexo…): o modal passa a mostrar o cartão atualizado. */
watch(modalPayload, () => {
    if (selectedCard.value) {
        syncSelectedCard(selectedCard.value.id);
    }
});
</script>

<template>
    <div class="space-y-3" data-task-board-screen>
        <h1 class="sr-only">Tarefas</h1>

        <div
            v-if="$page.props.flash?.success"
            class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-900"
        >
            {{ $page.props.flash.success }}
        </div>

        <template v-if="boards.length">
            <TaskBoardsNav
                v-model:search="search"
                :boards="visibleBoards"
                :all-boards="boards"
                :is-admin="isAdmin"
                :can-edit="canEdit"
                :show-route-name="showRouteName"
                @jump="jump"
                @expand-all="setAll(true)"
                @collapse-all="setAll(false)"
            />

            <section v-for="group in groups" :key="group.key" class="space-y-2" :aria-label="group.label">
                <h2 class="px-1 pt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ group.label }}</h2>
                <BoardSection
                    v-for="board in group.boards"
                    :key="board.id"
                    :board="board"
                    :payload="payloads[board.id] ?? null"
                    :expanded="isExpanded(board)"
                    :loading="Boolean(loading[board.id])"
                    :failed="Boolean(failed[board.id])"
                    :view-mode="viewModeOf(board.id)"
                    :show-archived="archived.has(board.id)"
                    :is-admin="isAdmin"
                    :company-users="companyUsers"
                    :team-users="teamUsers"
                    :companies="companies"
                    @toggle="toggle"
                    @need-payload="requestPayload"
                    @refresh="refresh"
                    @open-card="openCard"
                    @update:view-mode="setViewMode(board.id, $event)"
                    @toggle-archived="toggleArchived"
                />
            </section>

            <p v-if="!visibleBoards.length" class="surface-card p-6 text-sm text-slate-600">
                Nenhum quadro encontrado para essa busca.
            </p>

            <CardModal
                v-if="modalPayload"
                :show="modalOpen"
                :card="selectedCard"
                :board-payload="modalPayload"
                :company-users="companyUsers"
                :team-users="teamUsers"
                :companies="companies"
                :is-admin="isAdmin"
                :visibility-card-options="visibilityCardOptions"
                :recurrence-options="recurrenceOptions"
                @close="modalOpen = false"
                @refresh="refresh(selectedBoardId)"
                @sync-card="syncSelectedCard"
            />
        </template>

        <section v-else class="surface-card p-6 text-sm text-slate-600">
            {{ emptyMessage }}
        </section>
    </div>
</template>
