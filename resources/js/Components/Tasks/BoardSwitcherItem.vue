<script setup>
import BoardDescriptionToggle from '@/Components/Tasks/BoardDescriptionToggle.vue';
import { descriptionPlainText } from '@/utils/taskBoards';
import { Link } from '@inertiajs/vue3';
import { Bars3Icon, CheckIcon } from '@heroicons/vue/24/outline';
import { StarIcon } from '@heroicons/vue/24/solid';

/**
 * Item do seletor de quadros: o nome é um <Link> que cobre o item (troca de quadro sem recarregar a página;
 * Ctrl/Cmd+clique abre em nova aba). Alça de arrastar e «Ver descrição» ficam acima do link.
 */
defineProps({
    board: { type: Object, required: true },
    href: { type: String, required: true },
    active: { type: Boolean, default: false },
    draggable: { type: Boolean, default: false },
    showCompany: { type: Boolean, default: true },
});

const emit = defineEmits(['navigate']);

/** Props recarregadas na troca de quadro (o resto da página fica como está). */
const SWITCH_ONLY = ['boardPayload', 'boards', 'activity'];
</script>

<template>
    <li class="relative rounded-lg transition hover:bg-slate-50" :class="active ? 'bg-talents-50 hover:bg-talents-50' : ''">
        <div class="flex items-start gap-2 px-2 py-1.5">
            <button
                v-if="draggable"
                type="button"
                class="board-switcher-drag-handle relative z-10 mt-0.5 shrink-0 cursor-grab rounded p-0.5 text-slate-400 hover:bg-slate-200 hover:text-slate-600 active:cursor-grabbing"
                title="Arrastar para reordenar"
                aria-label="Arrastar para reordenar"
            >
                <Bars3Icon class="h-4 w-4" aria-hidden="true" />
            </button>
            <span
                class="mt-1 h-4 w-1 shrink-0 rounded-full"
                :class="board.cover_color ? '' : 'bg-slate-200'"
                :style="board.cover_color ? { backgroundColor: board.cover_color } : {}"
                aria-hidden="true"
            />
            <div class="min-w-0 flex-1">
                <Link
                    :href="href"
                    :only="SWITCH_ONLY"
                    preserve-state
                    preserve-scroll
                    class="block truncate text-sm font-medium text-slate-900 after:absolute after:inset-0 after:rounded-lg focus:outline-none focus-visible:after:ring-2 focus-visible:after:ring-inset focus-visible:after:ring-talents-500"
                    :aria-current="active ? 'page' : undefined"
                    @click="emit('navigate')"
                >
                    {{ board.name }}
                </Link>
                <p v-if="showCompany && board.company?.name" class="truncate text-[11px] text-slate-500">
                    {{ board.company.name }}
                </p>
                <BoardDescriptionToggle :description="descriptionPlainText(board.description)" />
            </div>
            <StarIcon v-if="board.is_starred" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-amber-400" aria-label="Favorito" />
            <CheckIcon v-if="active" class="mt-0.5 h-4 w-4 shrink-0 text-talents-700" aria-label="Quadro aberto" />
        </div>
    </li>
</template>
