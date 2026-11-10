<script setup>
import BoardHeader from '@/Components/Tasks/BoardHeader.vue';
import CardModal from '@/Components/Tasks/CardModal.vue';
import KanbanBoard from '@/Components/Tasks/KanbanBoard.vue';
import { confirmDialog } from '@/composables/useConfirmDialog';
import { formatDateNumeric, formatRelativeDate } from '@/utils/dateOnly';
import { router } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * Tela única de Tarefas (Admin e portal): barra compacta do quadro + Kanban/Lista do quadro ativo.
 * A troca de quadro é uma visita Inertia parcial à rota show (a URL acompanha o quadro aberto).
 */
const props = defineProps({
    isAdmin: { type: Boolean, default: false },
    /** Admin com tarefas:edit (reordenar quadros). */
    canEdit: { type: Boolean, default: false },
    boards: { type: Array, default: () => [] },
    boardPayload: { type: Object, default: null },
    companyUsers: { type: Array, default: () => [] },
    teamUsers: { type: Array, default: () => [] },
    companies: { type: Array, default: () => [] },
    visibilityCardOptions: { type: Array, default: () => [] },
    recurrenceOptions: { type: Array, default: () => [] },
    showRouteName: { type: String, required: true },
    emptyMessage: { type: String, default: 'Nenhum quadro disponível.' },
});

/** Props recarregadas ao atualizar ou trocar de quadro; usuários, empresas e opções ficam como estão. */
const BOARD_PROPS = ['boardPayload', 'boards', 'activity'];
const VIEW_MODE_KEY = props.isAdmin ? 'talents-tarefas-view-mode' : 'talents-client-tarefas-view-mode';

function readViewMode() {
    try {
        return localStorage.getItem(VIEW_MODE_KEY) === 'lista' ? 'lista' : 'kanban';
    } catch (_e) {
        return 'kanban';
    }
}

const viewMode = ref(readViewMode());
watch(viewMode, (value) => {
    try {
        localStorage.setItem(VIEW_MODE_KEY, value);
    } catch (_e) {
        // modo privado / cota: segue sem lembrar
    }
});

const modalOpen = ref(false);
const selectedCard = ref(null);

function openCard(card) {
    selectedCard.value = card;
    modalOpen.value = true;
}

function syncSelectedCard(cardId) {
    const freshCard = (props.boardPayload?.lists || [])
        .flatMap((list) => list.cards || [])
        .find((card) => Number(card.id) === Number(cardId));

    if (freshCard) {
        selectedCard.value = freshCard;
    }
}

const showArchived = computed(() => Boolean(props.boardPayload?.show_archived));

function visitBoard(params) {
    const main = document.querySelector('.app-shell-main-scroll');
    const mainTop = main?.scrollTop ?? 0;
    router.get(route(props.showRouteName, props.boardPayload.id), params, {
        preserveScroll: true,
        preserveState: true,
        only: BOARD_PROPS,
        onFinish: () => {
            requestAnimationFrame(() => {
                const el = document.querySelector('.app-shell-main-scroll');
                if (el) {
                    el.scrollTop = mainTop;
                }
            });
        },
    });
}

function refreshBoard() {
    visitBoard(showArchived.value ? { ver_arquivados: 1 } : {});
}

function toggleShowArchived() {
    visitBoard(showArchived.value ? {} : { ver_arquivados: 1 });
}

async function deleteListCard(card, event) {
    event?.stopPropagation?.();
    const title = card.title || 'esta tarefa';
    if (
        !(await confirmDialog(
            `Excluir "${title}"?\n\nA tarefa e todos os seus anexos, comentários e checklists serão removidos.`,
        ))
    ) {
        return;
    }
    router.delete(route('admin.tarefas.cards.destroy', card.id), {
        preserveScroll: true,
        onSuccess: () => refreshBoard(),
    });
}

const listCards = computed(() =>
    (props.boardPayload?.lists || []).flatMap((list) =>
        (list.cards || []).map((card) => ({
            ...card,
            list_name: list.name,
        })),
    ),
);

function formatDateTitle(value) {
    if (!value) return undefined;
    const relative = formatRelativeDate(value);
    const absolute = formatDateNumeric(value);
    if (!absolute || relative === absolute) return absolute;
    return `${relative} (${absolute})`;
}

/* Kanban do tamanho da janela (a partir de 640px): as colunas rolam por dentro e o quadro na horizontal. */
const FILL_MIN_WIDTH = 640;
const FILL_MIN_HEIGHT = 360;
const kanbanWrap = ref(null);
const kanbanHeight = ref(null);

function measureKanban() {
    const el = kanbanWrap.value;
    if (!el || window.innerWidth < FILL_MIN_WIDTH) {
        kanbanHeight.value = null;
        return;
    }
    // Topo do Kanban no documento (quem rola pode ser a janela ou o <main> do layout).
    const main = el.closest('.app-shell-main-scroll');
    const top = el.getBoundingClientRect().top + window.scrollY + (main?.scrollTop ?? 0);
    const bottomGap = main ? Number.parseFloat(getComputedStyle(main).paddingBottom) || 0 : 16;
    kanbanHeight.value = Math.max(FILL_MIN_HEIGHT, Math.floor(window.innerHeight - top - bottomGap - 8));
}

function remeasure() {
    nextTick(() => requestAnimationFrame(measureKanban));
}

onMounted(() => {
    remeasure();
    window.addEventListener('resize', measureKanban);
});

onBeforeUnmount(() => window.removeEventListener('resize', measureKanban));

watch(
    () => props.boardPayload?.id,
    () => {
        modalOpen.value = false;
        selectedCard.value = null;
        remeasure();
    },
);
watch(viewMode, remeasure);
</script>

<template>
    <div class="space-y-3" data-task-board-screen>
        <h1 class="sr-only">Tarefas{{ boardPayload ? ` — ${boardPayload.name}` : '' }}</h1>

        <div
            v-if="$page.props.flash?.success"
            class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-900"
        >
            {{ $page.props.flash.success }}
        </div>

        <template v-if="boardPayload">
            <BoardHeader
                :key="boardPayload.id"
                v-model:view-mode="viewMode"
                :board-payload="boardPayload"
                :boards="boards"
                :is-admin="isAdmin"
                :can-edit="canEdit"
                :show-route-name="showRouteName"
                :show-archived="showArchived"
                :company-users="companyUsers"
                :team-users="teamUsers"
                :companies="companies"
                @refresh="refreshBoard"
                @toggle-archived="toggleShowArchived"
            />

            <div
                v-if="viewMode === 'kanban'"
                ref="kanbanWrap"
                :style="kanbanHeight ? { height: `${kanbanHeight}px` } : {}"
                data-task-kanban-fill
            >
                <KanbanBoard
                    :key="boardPayload.id"
                    :board-payload="boardPayload"
                    :is-admin="isAdmin"
                    :companies="companies"
                    :fill-height="Boolean(kanbanHeight)"
                    @open-card="openCard"
                    @refresh="refreshBoard"
                />
            </div>

            <div v-else class="overflow-x-auto rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-600">
                            <th class="px-2 py-2">Tarefa</th>
                            <th class="px-2 py-2">Coluna</th>
                            <th v-if="isAdmin" class="px-2 py-2">Cliente</th>
                            <th class="px-2 py-2">Vencimento</th>
                            <th class="px-2 py-2">Status</th>
                            <th v-if="isAdmin" class="w-24 px-2 py-2 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="card in listCards"
                            :key="card.id"
                            class="cursor-pointer border-b border-slate-100 hover:bg-slate-50"
                            @click="openCard(card)"
                        >
                            <td class="px-2 py-2 font-medium text-slate-900">{{ card.title }}</td>
                            <td class="px-2 py-2 text-slate-600">{{ card.list_name }}</td>
                            <td v-if="isAdmin" class="px-2 py-2 text-slate-600">{{ card.company?.name || '—' }}</td>
                            <td class="px-2 py-2 text-slate-600" :title="formatDateTitle(card.due_date)">
                                {{ formatRelativeDate(card.due_date) }}
                            </td>
                            <td class="px-2 py-2 text-slate-600">
                                {{ card.completed_at ? 'Concluída' : 'Aberta' }}
                            </td>
                            <td v-if="isAdmin" class="px-2 py-2 text-right">
                                <button
                                    type="button"
                                    class="text-xs font-medium text-rose-600 hover:underline"
                                    @click="deleteListCard(card, $event)"
                                >
                                    Excluir
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!listCards.length">
                            <td :colspan="isAdmin ? 6 : 4" class="px-2 py-6 text-center text-sm text-slate-500">
                                Nenhuma tarefa neste quadro.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <CardModal
                :show="modalOpen"
                :card="selectedCard"
                :board-payload="boardPayload"
                :company-users="companyUsers"
                :team-users="teamUsers"
                :companies="companies"
                :is-admin="isAdmin"
                :visibility-card-options="visibilityCardOptions"
                :recurrence-options="recurrenceOptions"
                @close="modalOpen = false"
                @refresh="refreshBoard"
                @sync-card="syncSelectedCard"
            />
        </template>

        <section v-else class="surface-card p-6 text-sm text-slate-600">
            {{ emptyMessage }}
        </section>
    </div>
</template>
