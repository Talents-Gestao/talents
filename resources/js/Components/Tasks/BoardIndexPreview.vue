<script setup>
import TaskCardMeta from '@/Components/Tasks/TaskCardMeta.vue';
import { useCollapsedLists } from '@/composables/useCollapsedLists';
import { Link } from '@inertiajs/vue3';
import {
    ArrowsPointingOutIcon,
    ChevronDoubleLeftIcon,
    EllipsisHorizontalIcon,
} from '@heroicons/vue/24/outline';
import { onMounted, onUnmounted, ref } from 'vue';

defineProps({
    board: { type: Object, required: true },
    showRoute: { type: String, required: true },
    showCompanyOnCards: { type: Boolean, default: true },
});

const { isCollapsed: isListCollapsed, toggleCollapsed: toggleListCollapsed } = useCollapsedLists();

const previewMenuOpenId = ref(null);
const previewMenuPosition = ref(null);

function closePreviewMenu() {
    previewMenuOpenId.value = null;
    previewMenuPosition.value = null;
}

function togglePreviewMenu(list, event) {
    event?.stopPropagation?.();
    if (previewMenuOpenId.value === list.id) {
        closePreviewMenu();
        return;
    }

    const rect = event?.currentTarget?.getBoundingClientRect?.();
    if (!rect) {
        return;
    }

    const menuWidth = 160;
    const menuHeight = 44;
    const left = Math.max(8, Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8));

    let top = rect.bottom + 6;
    if (top + menuHeight > window.innerHeight - 8) {
        top = Math.max(8, rect.top - menuHeight - 6);
    }

    previewMenuOpenId.value = list.id;
    previewMenuPosition.value = {
        top,
        left,
        list,
    };
}

function collapseListFromPreviewMenu(list, event) {
    event?.stopPropagation?.();
    toggleListCollapsed(list.id);
    closePreviewMenu();
}

function expandList(list) {
    if (isListCollapsed(list.id)) {
        toggleListCollapsed(list.id);
    }
}

function listStyle(list) {
    const color = list?.color?.trim();
    if (!color) {
        return {};
    }
    return {
        borderTopWidth: '3px',
        borderTopStyle: 'solid',
        borderTopColor: color,
        backgroundColor: `${color}18`,
    };
}

onMounted(() => {
    document.addEventListener('click', closePreviewMenu);
    window.addEventListener('scroll', closePreviewMenu, true);
    window.addEventListener('resize', closePreviewMenu);
});

onUnmounted(() => {
    document.removeEventListener('click', closePreviewMenu);
    window.removeEventListener('scroll', closePreviewMenu, true);
    window.removeEventListener('resize', closePreviewMenu);
});
</script>

<template>
    <div class="border-t border-slate-100 bg-slate-50/50 px-3 py-3">
        <div class="mb-3 flex flex-wrap items-center gap-3 text-xs text-slate-500">
            <span>{{ board.lists_count }} lista(s)</span>
            <span>{{ board.cards_count }} tarefa(s)</span>
            <span v-if="board.updated_at">
                Atualizado
                {{
                    new Date(board.updated_at).toLocaleString('pt-BR', {
                        dateStyle: 'short',
                        timeStyle: 'short',
                    })
                }}
            </span>
        </div>

        <div v-if="!board.lists?.length" class="text-sm text-slate-500">Nenhuma lista neste quadro.</div>

        <div v-else class="flex gap-3 overflow-x-auto pb-1">
            <div
                v-for="list in board.lists"
                :key="list.id"
                class="flex shrink-0 flex-col rounded-lg p-2 ring-1 ring-slate-200 transition-all duration-200"
                :class="[
                    isListCollapsed(list.id) ? 'w-10 cursor-pointer' : 'w-64',
                    list.color ? '' : 'bg-white',
                ]"
                :style="listStyle(list)"
                @click="isListCollapsed(list.id) && expandList(list)"
            >
                <div
                    class="mb-2 flex items-start justify-between gap-1 px-1"
                    :class="isListCollapsed(list.id) ? 'mb-0 flex-col items-center' : ''"
                >
                    <div
                        v-if="isListCollapsed(list.id)"
                        class="flex min-h-[6rem] flex-col items-center justify-start gap-1 py-1"
                    >
                        <p
                            class="text-xs font-semibold uppercase tracking-wide text-slate-700"
                            style="writing-mode: vertical-rl; transform: rotate(180deg)"
                            :title="list.name"
                        >
                            {{ list.name }}
                        </p>
                        <span class="text-[10px] font-normal text-slate-500">({{ list.cards?.length ?? 0 }})</span>
                    </div>
                    <p
                        v-else
                        class="min-w-0 flex-1 truncate text-xs font-semibold uppercase tracking-wide text-slate-700"
                    >
                        {{ list.name }}
                        <span class="font-normal text-slate-500">({{ list.cards?.length ?? 0 }})</span>
                    </p>
                    <button
                        type="button"
                        class="shrink-0 rounded p-0.5 text-slate-500 transition hover:bg-slate-200 hover:text-slate-700"
                        title="Ações da lista"
                        @click.stop="togglePreviewMenu(list, $event)"
                    >
                        <EllipsisHorizontalIcon class="h-4 w-4" />
                    </button>
                </div>

                <ul v-if="!isListCollapsed(list.id) && list.cards?.length" class="space-y-2">
                    <li v-for="card in list.cards" :key="card.id">
                        <Link
                            :href="showRoute"
                            class="block rounded-md bg-white px-2.5 py-2 text-left shadow-sm ring-1 ring-slate-200 transition hover:ring-talents-300"
                        >
                            <div
                                v-if="card.cover_color"
                                class="-mx-2.5 -mt-2 mb-2 h-1.5 rounded-t-md"
                                :style="{ backgroundColor: card.cover_color }"
                            />

                            <div v-if="card.labels?.length" class="mb-1.5 flex flex-wrap gap-1">
                                <span
                                    v-for="lb in card.labels"
                                    :key="lb.id"
                                    class="inline-block h-2 min-w-[2rem] rounded-full"
                                    :style="{ backgroundColor: lb.color }"
                                    :title="lb.name || lb.color"
                                />
                            </div>

                            <p class="line-clamp-2 text-sm font-medium leading-snug text-slate-900">{{ card.title }}</p>
                            <p
                                v-if="showCompanyOnCards && card.company?.name"
                                class="mt-1 truncate text-[11px] text-slate-500"
                            >
                                {{ card.company.name }}
                            </p>

                            <TaskCardMeta :card="card" />
                        </Link>
                    </li>
                </ul>
                <p v-else-if="!isListCollapsed(list.id)" class="px-1 text-xs italic text-slate-400">Sem cartões</p>
            </div>
        </div>

        <Teleport to="body">
            <div
                v-if="previewMenuOpenId && previewMenuPosition"
                class="fixed z-[100] w-40 rounded-xl bg-white py-2 shadow-xl ring-1 ring-slate-200"
                :style="{
                    top: `${previewMenuPosition.top}px`,
                    left: `${previewMenuPosition.left}px`,
                }"
                @click.stop
            >
                <button
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-medium text-slate-700 hover:bg-slate-50"
                    @click="collapseListFromPreviewMenu(previewMenuPosition.list, $event)"
                >
                    <ChevronDoubleLeftIcon
                        v-if="!isListCollapsed(previewMenuPosition.list.id)"
                        class="h-3.5 w-3.5"
                    />
                    <ArrowsPointingOutIcon v-else class="h-3.5 w-3.5" />
                    {{ isListCollapsed(previewMenuPosition.list.id) ? 'Expandir lista' : 'Encolher lista' }}
                </button>
            </div>
        </Teleport>
    </div>
</template>
