<script setup>
import BoardSwitcherItem from '@/Components/Tasks/BoardSwitcherItem.vue';
import TaskToolbarMenu from '@/Components/Tasks/TaskToolbarMenu.vue';
import { filterBoards, groupBoards } from '@/utils/taskBoards';
import { router } from '@inertiajs/vue3';
import { ChevronUpDownIcon } from '@heroicons/vue/24/outline';
import { VueDraggable } from 'vue-draggable-plus';
import { computed, ref, watch } from 'vue';

/**
 * Seletor de quadros da tela única de Tarefas: busca, favoritos primeiro e os grupos de hoje
 * (internos / por empresa). No Admin, com tarefas:edit, os grupos podem ser reordenados (vale no portal).
 */
const props = defineProps({
    boards: { type: Array, default: () => [] },
    activeBoard: { type: Object, default: null },
    showRouteName: { type: String, required: true },
    isAdmin: { type: Boolean, default: false },
    canReorder: { type: Boolean, default: false },
});

const menu = ref(null);
const search = ref('');
const isSearching = computed(() => search.value.trim() !== '');
const groups = computed(() => groupBoards(filterBoards(props.boards, search.value)));

const localInternal = ref([]);
const localCompany = ref([]);

watch(
    () => [props.boards, search.value],
    () => {
        localInternal.value = groups.value.internal.map((b) => ({ ...b }));
        localCompany.value = groups.value.company.map((b) => ({ ...b }));
    },
    { immediate: true, deep: true },
);

const groupLabels = computed(() =>
    props.isAdmin
        ? { internal: 'Quadros internos', company: 'Quadros por empresa' }
        : { internal: 'Quadros da Talents', company: 'Quadros da empresa' },
);

const visibleScopes = computed(() =>
    [
        ['internal', localInternal.value],
        ['company', localCompany.value],
    ]
        .filter(([, list]) => list.length)
        .map(([scope]) => scope),
);

const draggable = (list) => props.canReorder && !isSearching.value && list.length > 1;

function hrefFor(board) {
    return route(props.showRouteName, board.id);
}

function persistOrder(scope, evt) {
    if (!props.canReorder || isSearching.value || (evt && evt.oldIndex === evt.newIndex)) {
        return;
    }
    const list = scope === 'internal' ? localInternal.value : localCompany.value;
    router.post(
        route('admin.tarefas.quadros.reordenar'),
        { scope, board_ids: list.map((board) => board.id) },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['boards'],
            onError: () => {
                localInternal.value = groups.value.internal.map((b) => ({ ...b }));
                localCompany.value = groups.value.company.map((b) => ({ ...b }));
            },
        },
    );
}

function closeMenu() {
    menu.value?.close();
}
</script>

<template>
    <TaskToolbarMenu
        ref="menu"
        :width="352"
        label="Trocar de quadro"
        title="Trocar de quadro"
        button-class="inline-flex min-w-0 max-w-[16rem] shrink-0 items-center gap-2 rounded-lg px-2 py-1 text-left transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500 sm:max-w-xs"
        @close="search = ''"
    >
        <template #trigger>
            <span
                class="h-5 w-1 shrink-0 rounded-full"
                :class="activeBoard?.cover_color ? '' : 'bg-slate-300'"
                :style="activeBoard?.cover_color ? { backgroundColor: activeBoard.cover_color } : {}"
                aria-hidden="true"
            />
            <span class="truncate text-sm font-semibold text-slate-900 sm:text-base">
                {{ activeBoard?.name || 'Escolher quadro' }}
            </span>
            <ChevronUpDownIcon class="h-4 w-4 shrink-0 text-slate-500" aria-hidden="true" />
        </template>

        <div class="px-2 pb-1.5 pt-0.5">
            <input
                v-model="search"
                data-autofocus
                type="search"
                placeholder="Buscar quadro ou empresa…"
                aria-label="Buscar quadro ou empresa"
                class="block w-full rounded-lg border-slate-300 px-2.5 py-1.5 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
            />
            <p v-if="canReorder && isSearching" class="mt-1 text-[11px] text-slate-500">Limpe a busca para reordenar os quadros.</p>
        </div>

        <section v-if="groups.favorites.length" class="px-1.5 pb-1">
            <h3 class="px-2 pb-0.5 pt-1 text-[10px] font-semibold uppercase tracking-wide text-slate-500">Favoritos</h3>
            <ul>
                <BoardSwitcherItem
                    v-for="board in groups.favorites"
                    :key="`fav-${board.id}`"
                    :board="board"
                    :href="hrefFor(board)"
                    :active="activeBoard?.id === board.id"
                    @navigate="closeMenu"
                />
            </ul>
        </section>

        <section v-for="scope in visibleScopes" :key="scope" class="border-t border-slate-100 px-1.5 py-1">
                <h3 class="px-2 pb-0.5 pt-1 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                    {{ groupLabels[scope] }}
                </h3>
                <VueDraggable
                    v-if="scope === 'internal'"
                    v-model="localInternal"
                    tag="ul"
                    handle=".board-switcher-drag-handle"
                    :disabled="!draggable(localInternal)"
                    :animation="150"
                    ghost-class="opacity-40"
                    @end="persistOrder('internal', $event)"
                >
                    <BoardSwitcherItem
                        v-for="board in localInternal"
                        :key="board.id"
                        :board="board"
                        :href="hrefFor(board)"
                        :active="activeBoard?.id === board.id"
                        :draggable="draggable(localInternal)"
                        @navigate="closeMenu"
                    />
                </VueDraggable>
                <VueDraggable
                    v-else
                    v-model="localCompany"
                    tag="ul"
                    handle=".board-switcher-drag-handle"
                    :disabled="!draggable(localCompany)"
                    :animation="150"
                    ghost-class="opacity-40"
                    @end="persistOrder('company', $event)"
                >
                    <BoardSwitcherItem
                        v-for="board in localCompany"
                        :key="board.id"
                        :board="board"
                        :href="hrefFor(board)"
                        :active="activeBoard?.id === board.id"
                        :draggable="draggable(localCompany)"
                        @navigate="closeMenu"
                    />
                </VueDraggable>
        </section>

        <p
            v-if="!groups.internal.length && !groups.company.length"
            class="px-4 py-3 text-sm text-slate-500"
        >
            {{ isSearching ? 'Nenhum quadro encontrado para essa busca.' : 'Nenhum quadro disponível.' }}
        </p>
    </TaskToolbarMenu>
</template>
