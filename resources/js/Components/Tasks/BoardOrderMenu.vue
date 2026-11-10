<script setup>
import TaskToolbarMenu from '@/Components/Tasks/TaskToolbarMenu.vue';
import { groupBoards } from '@/utils/taskBoards';
import { router } from '@inertiajs/vue3';
import { ArrowsUpDownIcon, Bars3Icon } from '@heroicons/vue/24/outline';
import { VueDraggable } from 'vue-draggable-plus';
import { computed, ref, watch } from 'vue';

/**
 * Ordem dos quadros (Admin com tarefas:edit): arrastar dentro de «Quadros internos» ou «Quadros por empresa».
 * A ordem vale para a tela de Tarefas e para o portal das empresas.
 */
const props = defineProps({
    boards: { type: Array, default: () => [] },
    buttonClass: { type: [String, Array, Object], default: '' },
});

const localInternal = ref([]);
const localCompany = ref([]);

function resetLocal() {
    const groups = groupBoards(props.boards);
    localInternal.value = groups.internal.map((b) => ({ ...b }));
    localCompany.value = groups.company.map((b) => ({ ...b }));
}

watch(() => props.boards, resetLocal, { immediate: true, deep: true });

const scopes = computed(() =>
    [
        { key: 'internal', label: 'Quadros internos', list: localInternal },
        { key: 'company', label: 'Quadros por empresa', list: localCompany },
    ].filter((scope) => scope.list.value.length),
);

function persistOrder(scope, evt) {
    if (evt && evt.oldIndex === evt.newIndex) {
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
            onError: resetLocal,
        },
    );
}
</script>

<template>
    <TaskToolbarMenu
        :width="320"
        label="Ordenar quadros"
        title="Ordenar quadros"
        align="right"
        :button-class="buttonClass"
    >
        <template #trigger>
            <ArrowsUpDownIcon class="h-4 w-4" aria-hidden="true" />
            <span class="hidden md:inline">Ordenar</span>
        </template>

        <p class="px-3 pb-1 pt-1 text-[11px] text-slate-500">Arraste para mudar a ordem. Vale também no portal das empresas.</p>
        <section v-for="scope in scopes" :key="scope.key" class="border-t border-slate-100 px-1.5 py-1">
            <h3 class="px-2 pb-0.5 pt-1 text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ scope.label }}</h3>
            <VueDraggable
                v-model="scope.list.value"
                tag="ul"
                handle=".board-order-handle"
                :disabled="scope.list.value.length < 2"
                :animation="150"
                ghost-class="opacity-40"
                @end="persistOrder(scope.key, $event)"
            >
                <li
                    v-for="board in scope.list.value"
                    :key="board.id"
                    class="board-order-handle flex cursor-grab items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-slate-800 hover:bg-slate-50 active:cursor-grabbing"
                >
                    <Bars3Icon class="h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" />
                    <span
                        class="h-4 w-1 shrink-0 rounded-full"
                        :class="board.cover_color ? '' : 'bg-slate-200'"
                        :style="board.cover_color ? { backgroundColor: board.cover_color } : {}"
                        aria-hidden="true"
                    />
                    <span class="min-w-0 flex-1 truncate">{{ board.name }}</span>
                    <span v-if="board.company?.name" class="max-w-[7rem] shrink-0 truncate text-[11px] text-slate-500">
                        {{ board.company.name }}
                    </span>
                </li>
            </VueDraggable>
        </section>
    </TaskToolbarMenu>
</template>
