<script setup>
import BoardOrderMenu from '@/Components/Tasks/BoardOrderMenu.vue';
import TaskToolbarMenu from '@/Components/Tasks/TaskToolbarMenu.vue';
import { Link } from '@inertiajs/vue3';
import { ChevronDoubleDownIcon, ChevronDoubleUpIcon, MagnifyingGlassIcon, PlusIcon } from '@heroicons/vue/24/outline';
import { StarIcon } from '@heroicons/vue/24/solid';

/**
 * Navegação do topo da tela de Tarefas: busca por quadro, atalhos para pular até cada quadro,
 * Expandir/Recolher todos e, no Admin, Ordenar e Novo (quadro interno, ativar processo, modelos).
 */
defineProps({
    /** Quadros que passam na busca, na ordem da tela. */
    boards: { type: Array, default: () => [] },
    /** Todos os quadros (para ordenar). */
    allBoards: { type: Array, default: () => [] },
    search: { type: String, default: '' },
    isAdmin: { type: Boolean, default: false },
    canEdit: { type: Boolean, default: false },
    showRouteName: { type: String, required: true },
});

const emit = defineEmits(['update:search', 'jump', 'expand-all', 'collapse-all']);

const button =
    'inline-flex shrink-0 items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500';
const menuItem =
    'flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none';

function jump(event, board) {
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.button === 1) {
        return; // nova aba: segue o link da rota do quadro
    }
    event.preventDefault();
    emit('jump', board.id);
}
</script>

<template>
    <nav
        class="sticky top-0 z-20 space-y-2 rounded-xl bg-white/95 px-2 py-2 shadow-sm ring-1 ring-slate-200 backdrop-blur supports-[backdrop-filter]:bg-white/80"
        aria-label="Quadros de tarefas"
        data-task-boards-nav
    >
        <div class="flex flex-wrap items-center gap-1.5">
            <label class="relative min-w-[12rem] flex-1 sm:max-w-xs">
                <span class="sr-only">Buscar quadro</span>
                <MagnifyingGlassIcon class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                <input
                    :value="search"
                    type="search"
                    placeholder="Buscar quadro ou empresa…"
                    class="block w-full rounded-lg border-slate-300 py-1.5 pl-8 pr-2.5 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    @input="emit('update:search', $event.target.value)"
                />
            </label>

            <span class="px-1 text-xs text-slate-500" aria-live="polite">
                {{ boards.length }} {{ boards.length === 1 ? 'quadro' : 'quadros' }}
            </span>

            <span class="min-w-2 flex-1" aria-hidden="true" />

            <button type="button" :class="button" title="Expandir todos os quadros" @click="emit('expand-all')">
                <ChevronDoubleDownIcon class="h-4 w-4" aria-hidden="true" />
                <span class="hidden sm:inline">Expandir todos</span>
                <span class="sr-only sm:hidden">Expandir todos</span>
            </button>
            <button type="button" :class="button" title="Recolher todos os quadros" @click="emit('collapse-all')">
                <ChevronDoubleUpIcon class="h-4 w-4" aria-hidden="true" />
                <span class="hidden sm:inline">Recolher todos</span>
                <span class="sr-only sm:hidden">Recolher todos</span>
            </button>

            <BoardOrderMenu v-if="isAdmin && canEdit && allBoards.length > 1" :boards="allBoards" :button-class="button" />

            <TaskToolbarMenu
                v-if="isAdmin"
                align="right"
                :width="240"
                label="Novo quadro ou processo"
                title="Novo quadro ou processo"
                :button-class="[button, 'bg-talents-600 text-white hover:bg-talents-700']"
            >
                <template #trigger>
                    <PlusIcon class="h-4 w-4" aria-hidden="true" />
                    <span class="hidden md:inline">Novo</span>
                </template>
                <template #default>
                    <Link :href="route('admin.tarefas.quadros.create')" :class="menuItem">Novo quadro interno</Link>
                    <Link :href="route('admin.tarefas.quadros.ativar')" :class="menuItem">Ativar processo para empresa</Link>
                    <Link :href="route('admin.tarefas.processos.index')" :class="menuItem">Modelos de processo</Link>
                </template>
            </TaskToolbarMenu>
        </div>

        <ul v-if="boards.length" class="flex gap-1.5 overflow-x-auto pb-0.5 [scrollbar-width:thin]" aria-label="Ir para o quadro">
            <li v-for="board in boards" :key="board.id" class="shrink-0">
                <a
                    :href="route(showRouteName, board.id)"
                    class="inline-flex max-w-[14rem] items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 transition hover:border-talents-300 hover:bg-talents-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500"
                    @click="jump($event, board)"
                >
                    <span
                        class="h-2 w-2 shrink-0 rounded-full"
                        :class="board.cover_color ? '' : 'bg-slate-300'"
                        :style="board.cover_color ? { backgroundColor: board.cover_color } : {}"
                        aria-hidden="true"
                    />
                    <StarIcon v-if="board.is_starred" class="h-3 w-3 shrink-0 text-amber-400" aria-label="Favorito" />
                    <span class="truncate">{{ board.name }}</span>
                </a>
            </li>
        </ul>
    </nav>
</template>
