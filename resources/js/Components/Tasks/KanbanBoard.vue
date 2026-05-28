<script setup>
import TextInput from '@/Components/TextInput.vue';
import TaskCardMeta from '@/Components/Tasks/TaskCardMeta.vue';
import { router } from '@inertiajs/vue3';
import {
    ArrowsPointingOutIcon,
    ChevronDoubleLeftIcon,
    EllipsisHorizontalIcon,
    PencilSquareIcon,
    PlusIcon,
    TrashIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import { useCollapsedLists } from '@/composables/useCollapsedLists';
import { VueDraggable } from 'vue-draggable-plus';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    boardPayload: { type: Object, required: true },
    isAdmin: { type: Boolean, default: false },
    /** Empresas ativas (admin); necessário para atribuir cliente em quadro global. */
    companies: { type: Array, default: () => [] },
});

const emit = defineEmits(['open-card', 'refresh']);

const { isCollapsed: isListCollapsed, toggleCollapsed: toggleListCollapsed } = useCollapsedLists();

const LIST_COLOR_PRESETS = [
    { value: '#ef4444', label: 'Vermelho' },
    { value: '#f97316', label: 'Laranja' },
    { value: '#eab308', label: 'Amarelo' },
    { value: '#22c55e', label: 'Verde' },
    { value: '#14b8a6', label: 'Turquesa' },
    { value: '#3b82f6', label: 'Azul' },
    { value: '#8b5cf6', label: 'Roxo' },
    { value: '#ec4899', label: 'Rosa' },
];

const localLists = ref(cloneLists(props.boardPayload.lists));

watch(
    () => props.boardPayload,
    (payload) => {
        if (payload?.lists) {
            localLists.value = cloneLists(payload.lists);
        }
    },
    { deep: true },
);

const labels = computed(() => props.boardPayload.labels ?? []);

const needsClientCompanyOnQuickAdd = computed(
    () => props.isAdmin && !props.boardPayload?.company_id,
);

const quickAddCompanyId = ref('');

function cloneLists(lists) {
    if (!lists) return [];
    return JSON.parse(JSON.stringify(lists));
}

function reload() {
    emit('refresh');
}

const listMenuOpenId = ref(null);
const listMenuPosition = ref(null);
const editingListId = ref(null);
const editingListName = ref('');

function closeListMenu() {
    listMenuOpenId.value = null;
    listMenuPosition.value = null;
}

function toggleListMenu(list, event) {
    event?.stopPropagation?.();
    if (listMenuOpenId.value === list.id) {
        closeListMenu();
        return;
    }

    const rect = event?.currentTarget?.getBoundingClientRect?.();
    if (!rect) {
        return;
    }

    const menuWidth = 192;
    const menuHeight = 148;
    const left = Math.max(8, Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8));

    let top = rect.bottom + 6;
    if (top + menuHeight > window.innerHeight - 8) {
        top = Math.max(8, rect.top - menuHeight - 6);
    }

    listMenuOpenId.value = list.id;
    listMenuPosition.value = {
        top,
        left,
        list,
    };
}

function onListMenuViewportChange() {
    if (listMenuOpenId.value) {
        closeListMenu();
    }
}

function hexToRgba(hex, alpha) {
    let h = String(hex || '').replace('#', '').trim();
    if (h.length === 3) {
        h = h
            .split('')
            .map((c) => c + c)
            .join('');
    }
    if (h.length !== 6) return null;
    const n = Number.parseInt(h, 16);
    if (Number.isNaN(n)) return null;
    const r = (n >> 16) & 255;
    const g = (n >> 8) & 255;
    const b = n & 255;
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

function listColumnStyle(list) {
    const color = list?.color?.trim();
    if (!color) {
        return {};
    }
    const tint = hexToRgba(color, 0.12);
    return {
        borderTopWidth: '3px',
        borderTopStyle: 'solid',
        borderTopColor: color,
        backgroundColor: tint || '#f1f5f9',
    };
}

function isListColorSelected(list, color) {
    const current = (list?.color || '').toLowerCase();
    const next = (color || '').toLowerCase();
    if (!next) return !current;
    return current === next;
}

function startRenameList(list, event) {
    event?.stopPropagation?.();
    if (!props.isAdmin || !list?.id) {
        return;
    }
    closeListMenu();
    editingListId.value = list.id;
    editingListName.value = list.name || '';
    nextTick(() => {
        document.querySelector(`[data-list-rename-input="${list.id}"]`)?.focus();
    });
}

function cancelRenameList() {
    editingListId.value = null;
    editingListName.value = '';
}

function submitRenameList(list) {
    if (!props.isAdmin || !list?.id) {
        return;
    }

    const name = editingListName.value.trim();
    if (!name) {
        cancelRenameList();
        return;
    }

    if (name === (list.name || '').trim()) {
        cancelRenameList();
        return;
    }

    router.patch(
        route('admin.tarefas.listas.update', list.id),
        { name },
        {
            preserveScroll: true,
            onSuccess: () => {
                list.name = name;
                cancelRenameList();
                reload();
            },
        },
    );
}

function setListColor(list, color, event) {
    event?.stopPropagation?.();
    if (!props.isAdmin || !list?.id) return;

    const normalized = color?.trim() || null;
    const current = list.color?.trim() || null;
    if (current === normalized) {
        return;
    }

    router.patch(
        route('admin.tarefas.listas.update', list.id),
        { color: normalized },
        {
            preserveScroll: true,
            onSuccess: () => {
                list.color = normalized;
                reload();
            },
        },
    );
}

function deleteList(list, event) {
    event?.stopPropagation?.();
    if (!props.isAdmin || !list?.id) return;

    closeListMenu();

    const name = list.name || 'esta lista';
    const cardCount = list.cards?.length ?? 0;
    const cardsWarning =
        cardCount > 0
            ? `\n\n${cardCount} tarefa(s) nesta coluna também serão removidas.`
            : '';

    if (
        !window.confirm(
            `Excluir a lista "${name}"?${cardsWarning}\n\nEsta ação não pode ser desfeita.`,
        )
    ) {
        return;
    }

    router.delete(route('admin.tarefas.listas.destroy', list.id), {
        preserveScroll: true,
        onSuccess: () => reload(),
    });
}

onMounted(() => {
    document.addEventListener('click', closeListMenu);
    window.addEventListener('scroll', onListMenuViewportChange, true);
    window.addEventListener('resize', onListMenuViewportChange);
});
onUnmounted(() => {
    document.removeEventListener('click', closeListMenu);
    window.removeEventListener('scroll', onListMenuViewportChange, true);
    window.removeEventListener('resize', onListMenuViewportChange);
});

function toggleCardComplete(card, event) {
    event?.stopPropagation?.();
    if (!props.isAdmin || !card?.id) return;

    const completing = !card.completed_at;
    const previousCompletedAt = card.completed_at;

    card.completed_at = completing ? new Date().toISOString() : null;

    router.patch(
        route('admin.tarefas.cards.update', card.id),
        { complete: completing },
        {
            preserveScroll: true,
            onSuccess: () => reload(),
            onError: () => {
                card.completed_at = previousCompletedAt;
            },
        },
    );
}

function requestDeleteCard(card) {
    if (!props.isAdmin || !card?.id) return;
    const title = card.title || 'esta tarefa';
    if (
        !window.confirm(
            `Excluir "${title}"?\n\nA tarefa e todos os seus anexos, comentários e checklists serão removidos.`,
        )
    ) {
        return;
    }

    router.delete(route('admin.tarefas.cards.destroy', card.id), {
        preserveScroll: true,
        onSuccess: () => reload(),
    });
}

function moveCardRoute(cardId) {
    return props.isAdmin
        ? route('admin.tarefas.cards.move', cardId)
        : route('client.tarefas.cards.move', cardId);
}

function positionBetween(list, newIndex) {
    const cards = list.cards;
    const prev = newIndex > 0 ? Number(cards[newIndex - 1].position) : 0;
    const next =
        newIndex < cards.length - 1 ? Number(cards[newIndex + 1].position) : prev + 2000;
    if (newIndex === 0 && cards.length > 1) {
        const first = Number(cards[1].position);
        return first > 0 ? first / 2 : 500;
    }
    if (newIndex >= cards.length - 1) {
        return prev + 1000;
    }
    return (prev + next) / 2;
}

function onCardDragEnd(_fromListId, evt) {
    if (!props.isAdmin) return;
    const cardEl = evt.item;
    const cardId = Number(cardEl?.dataset?.cardId);
    if (!cardId) return;

    const fromEl = evt.from?.closest?.('[data-list-id]');
    const toEl = evt.to?.closest?.('[data-list-id]');
    const fromId = Number(fromEl?.dataset?.listId);
    const toId = Number(toEl?.dataset?.listId);
    if (!fromId || !toId) return;

    const targetList = localLists.value.find((l) => l.id === toId);
    if (!targetList) return;

    const newIndex = Array.from(evt.to.children).indexOf(cardEl);
    const idx = newIndex >= 0 ? newIndex : targetList.cards.length - 1;
    const position = positionBetween(targetList, idx);

    router.post(
        moveCardRoute(cardId),
        { list_id: toId, position },
        {
            preserveScroll: true,
            onSuccess: () => reload(),
        },
    );
}

const newListName = ref('');
const showNewListInput = ref(false);

function submitNewList() {
    if (!newListName.value.trim()) return;
    router.post(
        route('admin.tarefas.quadros.listas.store', props.boardPayload.id),
        {
            name: newListName.value.trim(),
            visibility: 'company',
            allow_company_drop_in: true,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                newListName.value = '';
                showNewListInput.value = false;
                reload();
            },
        },
    );
}

const composing = ref({});
const newCardTitles = ref({});

function startComposing(listId) {
    composing.value[listId] = true;
    if (newCardTitles.value[listId] === undefined) {
        newCardTitles.value[listId] = '';
    }
}

function cancelComposing(listId) {
    composing.value[listId] = false;
    newCardTitles.value[listId] = '';
}

function submitNewCard(list) {
    const key = list.id;
    const title = (newCardTitles.value[key] || '').trim();
    if (!title) return;

    const payload = {
        title,
        visibility: 'inherit',
    };
    if (props.boardPayload.company_id) {
        payload.company_id = props.boardPayload.company_id;
    } else if (list.visibility === 'company' && quickAddCompanyId.value) {
        payload.company_id = Number(quickAddCompanyId.value);
    }

    router.post(
        route('admin.tarefas.listas.cards.store', list.id),
        payload,
        {
            preserveScroll: true,
            onSuccess: () => {
                newCardTitles.value[key] = '';
                quickAddCompanyId.value = '';
                reload();
            },
        },
    );
}

function openCard(card) {
    emit('open-card', card);
}

function collapseListFromMenu(list, event) {
    event?.stopPropagation?.();
    toggleListCollapsed(list.id);
    closeListMenu();
}

function expandList(list) {
    if (isListCollapsed(list.id)) {
        toggleListCollapsed(list.id);
    }
}

</script>

<template>
    <div class="space-y-3">
        <div class="flex items-start gap-3 overflow-x-auto pb-3">
            <div
                v-for="list in localLists"
                :key="list.id"
                :data-list-id="list.id"
                class="flex shrink-0 flex-col rounded-xl p-2 shadow-sm ring-1 ring-slate-200 transition-all duration-200"
                :class="[
                    isListCollapsed(list.id) ? 'w-10 cursor-pointer' : 'w-72',
                    list.color ? '' : 'bg-slate-100',
                ]"
                :style="listColumnStyle(list)"
                @click="isListCollapsed(list.id) && expandList(list)"
            >
                <header
                    class="flex items-start justify-between gap-2 px-1.5 pb-1 pt-1"
                    :class="isListCollapsed(list.id) ? 'flex-col items-center gap-1' : ''"
                >
                    <div
                        v-if="isListCollapsed(list.id)"
                        class="flex min-h-[8rem] flex-1 flex-col items-center justify-start gap-1 py-1"
                    >
                        <p
                            class="text-xs font-semibold text-slate-900"
                            style="writing-mode: vertical-rl; transform: rotate(180deg)"
                            :title="list.name"
                        >
                            {{ list.name }}
                        </p>
                        <span class="text-[10px] font-normal text-slate-500">({{ list.cards?.length ?? 0 }})</span>
                    </div>
                    <div v-else class="min-w-0 flex-1">
                        <input
                            v-if="isAdmin && editingListId === list.id"
                            :data-list-rename-input="list.id"
                            v-model="editingListName"
                            type="text"
                            class="block w-full rounded-md border-slate-300 bg-white px-2 py-1 text-sm font-semibold text-slate-900 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            @click.stop
                            @keydown.enter.prevent="submitRenameList(list)"
                            @keydown.esc.prevent="cancelRenameList"
                            @blur="submitRenameList(list)"
                        />
                        <h3
                            v-else
                            class="truncate text-sm font-semibold text-slate-900"
                            :class="isAdmin ? 'cursor-text rounded px-1 py-0.5 hover:bg-white/60' : ''"
                            :title="isAdmin ? 'Clique para renomear' : undefined"
                            @click.stop="isAdmin && startRenameList(list, $event)"
                        >
                            {{ list.name }}
                        </h3>
                        <p
                            v-if="isAdmin && editingListId !== list.id"
                            class="mt-0.5 text-[10px] uppercase tracking-wide text-slate-500"
                        >
                            {{ list.visibility }} · drop-in
                            {{ list.allow_company_drop_in ? 'sim' : 'não' }}
                        </p>
                    </div>
                    <div v-if="isAdmin" class="relative shrink-0">
                        <button
                            type="button"
                            class="rounded p-1 text-slate-500 transition hover:bg-slate-200 hover:text-slate-700"
                            :class="listMenuOpenId === list.id ? 'bg-slate-200 text-slate-800' : ''"
                            title="Ações da lista"
                            @click.stop="toggleListMenu(list, $event)"
                        >
                            <EllipsisHorizontalIcon class="h-4 w-4" />
                        </button>
                    </div>
                </header>

                <VueDraggable
                    v-if="!isListCollapsed(list.id)"
                    v-model="list.cards"
                    group="kanban-cards"
                    item-key="id"
                    :disabled="!isAdmin"
                    class="flex min-h-[8px] flex-col gap-2 px-0.5"
                    @end="(e) => onCardDragEnd(list.id, e)"
                >
                    <div
                        v-for="card in list.cards"
                        :key="card.id"
                        :data-card-id="card.id"
                        class="group relative cursor-pointer rounded-lg bg-white px-3 py-2 text-left shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:ring-talents-300"
                        @click="openCard(card)"
                    >
                        <button
                            v-if="isAdmin"
                            type="button"
                            class="absolute right-1 top-1 z-10 rounded-md bg-white/90 p-1 text-rose-600 opacity-0 shadow-sm ring-1 ring-slate-200 transition hover:bg-rose-50 group-hover:opacity-100"
                            title="Excluir tarefa"
                            @click.stop="requestDeleteCard(card)"
                        >
                            <TrashIcon class="h-3.5 w-3.5" />
                        </button>
                        <div v-if="card.cover_color" class="-mx-3 -mt-2 mb-2 h-2 rounded-t-lg" :style="{ backgroundColor: card.cover_color }" />

                        <div v-if="card.labels?.length" class="mb-1.5 flex flex-wrap gap-1">
                            <span
                                v-for="lb in card.labels"
                                :key="lb.id"
                                class="inline-block h-2 min-w-[2.5rem] rounded-full"
                                :style="{ backgroundColor: lb.color }"
                                :title="lb.name || lb.color"
                            />
                        </div>

                        <div class="flex items-start gap-2">
                            <input
                                v-if="isAdmin"
                                type="checkbox"
                                class="mt-0.5 h-4 w-4 shrink-0 rounded-full border-slate-300 text-talents-600 focus:ring-talents-500"
                                :checked="!!card.completed_at"
                                :title="card.completed_at ? 'Reabrir tarefa' : 'Marcar como concluída'"
                                :aria-label="card.completed_at ? 'Reabrir tarefa' : 'Marcar como concluída'"
                                @click.stop="toggleCardComplete(card, $event)"
                            />
                            <p
                                class="min-w-0 flex-1 text-sm font-medium leading-snug"
                                :class="
                                    card.completed_at ? 'text-slate-500 line-through' : 'text-slate-900'
                                "
                            >
                                {{ card.title }}
                            </p>
                        </div>
                        <p v-if="isAdmin && card.company?.name" class="mt-1 text-[11px] text-slate-500">
                            Cliente: {{ card.company.name }}
                        </p>

                        <TaskCardMeta :card="card" />
                    </div>
                </VueDraggable>

                <div v-if="isAdmin && !isListCollapsed(list.id)" class="px-0.5 pt-2">
                    <div v-if="composing[list.id]" class="space-y-2">
                        <div
                            v-if="
                                list.visibility === 'company' &&
                                needsClientCompanyOnQuickAdd &&
                                companies.length
                            "
                        >
                            <label class="block text-[11px] font-medium text-slate-600">
                                Empresa (opcional)
                                <span class="font-normal text-slate-500">— para exibir no portal do cliente</span>
                                <select
                                    v-model="quickAddCompanyId"
                                    class="mt-1 block w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                >
                                    <option value="">Nenhuma / depois</option>
                                    <option v-for="c in companies" :key="c.id" :value="String(c.id)">
                                        {{ c.name }}
                                    </option>
                                </select>
                            </label>
                        </div>
                        <textarea
                            v-model="newCardTitles[list.id]"
                            rows="2"
                            class="block w-full resize-none rounded-lg border-slate-300 bg-white text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            placeholder="Digite um título ou cole um link"
                            @keydown.enter.prevent="submitNewCard(list)"
                        />
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-lg bg-talents-600 px-3 py-1.5 text-xs font-semibold text-white shadow hover:bg-talents-700"
                                @click="submitNewCard(list)"
                            >
                                Adicionar cartão
                            </button>
                            <button
                                type="button"
                                class="rounded-lg p-1 text-slate-500 hover:bg-slate-200 hover:text-slate-700"
                                title="Cancelar"
                                @click="cancelComposing(list.id)"
                            >
                                <XMarkIcon class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                    <button
                        v-else
                        type="button"
                        class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-xs font-medium text-slate-600 transition hover:bg-slate-200"
                        @click="startComposing(list.id)"
                    >
                        <PlusIcon class="h-4 w-4" />
                        Adicionar um cartão
                    </button>
                </div>
            </div>

            <div v-if="isAdmin" class="w-72 shrink-0">
                <div v-if="showNewListInput" class="space-y-2 rounded-xl bg-slate-100 p-2 shadow-sm ring-1 ring-slate-200">
                    <TextInput
                        v-model="newListName"
                        placeholder="Insira o nome da lista…"
                        class="block w-full text-sm"
                        @keyup.enter="submitNewList"
                    />
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-lg bg-talents-600 px-3 py-1.5 text-xs font-semibold text-white shadow hover:bg-talents-700"
                            @click="submitNewList"
                        >
                            Adicionar lista
                        </button>
                        <button
                            type="button"
                            class="rounded-lg p-1 text-slate-500 hover:bg-slate-200 hover:text-slate-700"
                            title="Cancelar"
                            @click="showNewListInput = false; newListName = ''"
                        >
                            <XMarkIcon class="h-4 w-4" />
                        </button>
                    </div>
                </div>
                <button
                    v-else
                    type="button"
                    class="flex w-full items-center gap-2 rounded-xl bg-slate-100 px-3 py-2 text-sm font-medium text-slate-600 ring-1 ring-slate-200 transition hover:bg-slate-200 hover:text-slate-800"
                    @click="showNewListInput = true"
                >
                    <PlusIcon class="h-4 w-4" />
                    Adicionar outra lista
                </button>
            </div>
        </div>

        <div v-if="isAdmin && labels.length" class="text-xs text-slate-500">
            <span class="font-medium">Etiquetas no quadro:</span>
            <span class="ml-1 inline-flex flex-wrap gap-1.5">
                <span
                    v-for="l in labels"
                    :key="l.id"
                    class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5"
                >
                    <span class="inline-block h-2 w-2 rounded-full" :style="{ backgroundColor: l.color }" />
                    {{ l.name || l.color }}
                </span>
            </span>
        </div>

        <Teleport to="body">
            <div
                v-if="listMenuOpenId && listMenuPosition"
                class="fixed z-[100] w-48 rounded-xl bg-white py-2 shadow-xl ring-1 ring-slate-200"
                :style="{
                    top: `${listMenuPosition.top}px`,
                    left: `${listMenuPosition.left}px`,
                }"
                @click.stop
            >
                <button
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-medium text-slate-700 hover:bg-slate-50"
                    @click="collapseListFromMenu(listMenuPosition.list, $event)"
                >
                    <ChevronDoubleLeftIcon
                        v-if="!isListCollapsed(listMenuPosition.list.id)"
                        class="h-3.5 w-3.5"
                    />
                    <ArrowsPointingOutIcon v-else class="h-3.5 w-3.5" />
                    {{ isListCollapsed(listMenuPosition.list.id) ? 'Expandir lista' : 'Encolher lista' }}
                </button>
                <button
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-medium text-slate-700 hover:bg-slate-50"
                    @click="startRenameList(listMenuPosition.list, $event)"
                >
                    <PencilSquareIcon class="h-3.5 w-3.5" />
                    Renomear lista
                </button>
                <div class="border-b border-slate-100 px-3 pb-2">
                    <p class="text-[10px] font-medium uppercase tracking-wide text-slate-500">
                        Cor da lista
                    </p>
                    <div class="mt-2 grid grid-cols-5 gap-2">
                        <button
                            type="button"
                            class="h-7 w-7 justify-self-center rounded-full ring-2 ring-offset-1 transition hover:scale-105"
                            :class="
                                isListColorSelected(listMenuPosition.list, '')
                                    ? 'ring-talents-500'
                                    : 'ring-transparent'
                            "
                            style="background: linear-gradient(135deg, #f1f5f9 50%, #e2e8f0 50%)"
                            title="Sem cor"
                            @click="setListColor(listMenuPosition.list, '', $event)"
                        />
                        <button
                            v-for="preset in LIST_COLOR_PRESETS"
                            :key="preset.value"
                            type="button"
                            class="h-7 w-7 justify-self-center rounded-full ring-2 ring-offset-1 transition hover:scale-105"
                            :class="
                                isListColorSelected(listMenuPosition.list, preset.value)
                                    ? 'ring-talents-500'
                                    : 'ring-transparent'
                            "
                            :style="{ backgroundColor: preset.value }"
                            :title="preset.label"
                            @click="setListColor(listMenuPosition.list, preset.value, $event)"
                        />
                    </div>
                </div>
                <button
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-medium text-rose-600 hover:bg-rose-50"
                    @click="deleteList(listMenuPosition.list, $event)"
                >
                    <TrashIcon class="h-3.5 w-3.5" />
                    Excluir lista
                </button>
            </div>
        </Teleport>
    </div>
</template>
