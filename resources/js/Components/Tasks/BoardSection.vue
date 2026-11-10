<script setup>
import BoardHeader from '@/Components/Tasks/BoardHeader.vue';
import BoardListTable from '@/Components/Tasks/BoardListTable.vue';
import BoardSummary from '@/Components/Tasks/BoardSummary.vue';
import KanbanBoard from '@/Components/Tasks/KanbanBoard.vue';
import { kanbanNeedsFixedHeight } from '@/utils/taskBoards';
import { computed, onBeforeUnmount, onMounted, ref, watchEffect } from 'vue';

/**
 * Um quadro da tela de Tarefas, recolhível: cabeçalho sempre visível; recolhido mostra o resumo,
 * expandido mostra o Kanban (ou a Lista). O conteúdo é pedido (`need-payload`) só quando a seção está
 * expandida e perto da área visível; até chegar, um esqueleto ocupa o lugar.
 */
const props = defineProps({
    board: { type: Object, required: true },
    payload: { type: Object, default: null },
    expanded: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    failed: { type: Boolean, default: false },
    viewMode: { type: String, default: 'kanban' },
    showArchived: { type: Boolean, default: false },
    isAdmin: { type: Boolean, default: false },
    companyUsers: { type: Array, default: () => [] },
    teamUsers: { type: Array, default: () => [] },
    companies: { type: Array, default: () => [] },
});

const emit = defineEmits(['toggle', 'need-payload', 'refresh', 'open-card', 'update:viewMode', 'toggle-archived']);

const bodyId = computed(() => `quadro-${props.board.id}-conteudo`);

/* Perto da área visível (com folga): só então o conteúdo é pedido. */
const root = ref(null);
const nearViewport = ref(typeof IntersectionObserver === 'undefined');
let observer = null;

onMounted(() => {
    if (typeof IntersectionObserver === 'undefined' || !root.value) {
        return;
    }
    observer = new IntersectionObserver(
        (entries) => {
            nearViewport.value = entries.some((entry) => entry.isIntersecting);
        },
        { rootMargin: '400px 0px' },
    );
    observer.observe(root.value);
});

onBeforeUnmount(() => observer?.disconnect());

watchEffect(() => {
    if (props.expanded && !props.payload && !props.loading && !props.failed && nearViewport.value) {
        emit('need-payload', props.board.id);
    }
});

/* Kanban com altura máxima e rolagem interna (a partir de 640px) quando alguma lista é longa. */
const wide = ref(typeof window !== 'undefined' && window.matchMedia?.('(min-width: 640px)').matches);
let media = null;
const onMedia = (event) => {
    wide.value = event.matches;
};
onMounted(() => {
    media = window.matchMedia?.('(min-width: 640px)');
    media?.addEventListener?.('change', onMedia);
});
onBeforeUnmount(() => media?.removeEventListener?.('change', onMedia));

const fillHeight = computed(() => Boolean(wide.value && props.payload && kanbanNeedsFixedHeight(props.payload)));
</script>

<template>
    <section
        :id="`quadro-${board.id}`"
        ref="root"
        class="scroll-mt-36 rounded-xl bg-white shadow-sm ring-1 ring-slate-200"
        :data-board-section="board.id"
    >
        <BoardHeader
            :board="board"
            :board-payload="payload"
            :expanded="expanded"
            :body-id="bodyId"
            :view-mode="viewMode"
            :show-archived="showArchived"
            :is-admin="isAdmin"
            :company-users="companyUsers"
            :team-users="teamUsers"
            :companies="companies"
            @toggle="emit('toggle', board.id)"
            @refresh="emit('refresh', board.id)"
            @update:view-mode="emit('update:viewMode', $event)"
            @toggle-archived="emit('toggle-archived', board.id)"
        />

        <div v-if="!expanded" class="border-t border-slate-100 px-3 py-2">
            <BoardSummary :board="board" />
        </div>

        <div v-else :id="bodyId" class="border-t border-slate-100 p-2 sm:p-3">
            <div v-if="!payload && failed" class="flex flex-wrap items-center gap-3 px-2 py-6 text-sm text-slate-600">
                <span>Não foi possível carregar este quadro.</span>
                <button
                    type="button"
                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    @click="emit('need-payload', board.id)"
                >
                    Tentar de novo
                </button>
            </div>

            <div v-else-if="!payload" class="flex gap-3 overflow-hidden" aria-busy="true" data-board-skeleton>
                <span class="sr-only">Carregando o quadro…</span>
                <div
                    v-for="n in Math.min(Math.max(Number(board.lists_count) || 3, 1), 4)"
                    :key="n"
                    class="w-72 shrink-0 space-y-2 rounded-xl bg-slate-100 p-3 motion-safe:animate-pulse"
                    aria-hidden="true"
                >
                    <div class="h-3 w-24 rounded bg-slate-200" />
                    <div class="h-14 rounded-lg bg-white/80" />
                    <div class="h-14 rounded-lg bg-white/80" />
                </div>
            </div>

            <template v-else>
                <div
                    v-if="viewMode === 'kanban'"
                    :style="fillHeight ? { height: 'min(70vh, 46rem)' } : {}"
                    data-task-kanban-fill
                >
                    <KanbanBoard
                        :board-payload="payload"
                        :is-admin="isAdmin"
                        :companies="companies"
                        :fill-height="fillHeight"
                        @open-card="emit('open-card', board.id, $event)"
                        @refresh="emit('refresh', board.id)"
                    />
                </div>
                <BoardListTable
                    v-else
                    :board-payload="payload"
                    :is-admin="isAdmin"
                    @open-card="emit('open-card', board.id, $event)"
                    @refresh="emit('refresh', board.id)"
                />
            </template>
        </div>
    </section>
</template>
