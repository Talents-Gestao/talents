<script setup>
import TextInput from '@/Components/TextInput.vue';
import { router } from '@inertiajs/vue3';
import {
    Bars3BottomLeftIcon,
    CalendarDaysIcon,
    ChatBubbleOvalLeftEllipsisIcon,
    CheckCircleIcon,
    EllipsisHorizontalIcon,
    PaperClipIcon,
    PlusIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import { VueDraggable } from 'vue-draggable-plus';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    boardPayload: { type: Object, required: true },
    isAdmin: { type: Boolean, default: false },
});

const emit = defineEmits(['open-card', 'refresh']);

const localLists = ref(cloneLists(props.boardPayload.lists));

watch(
    () => props.boardPayload.lists,
    (lists) => {
        localLists.value = cloneLists(lists);
    },
    { deep: true },
);

const labels = computed(() => props.boardPayload.labels ?? []);

function cloneLists(lists) {
    if (!lists) return [];
    return JSON.parse(JSON.stringify(lists));
}

function reload() {
    emit('refresh');
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
    router.post(
        route('admin.tarefas.listas.cards.store', list.id),
        {
            title,
            visibility: 'inherit',
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                newCardTitles.value[key] = '';
                reload();
            },
        },
    );
}

function openCard(card) {
    emit('open-card', card);
}

function checklistTotals(card) {
    if (!card.checklists?.length) return null;
    let total = 0;
    let done = 0;
    card.checklists.forEach((cl) => {
        cl.items?.forEach((it) => {
            total += 1;
            if (it.is_completed) done += 1;
        });
    });
    if (total === 0) return null;
    return { done, total, complete: done === total };
}

function descriptionPresent(card) {
    const d = card.description;
    return typeof d === 'string' ? d.trim().length > 0 : Boolean(d);
}

function avatarInitials(name) {
    if (!name) return '?';
    const parts = String(name).trim().split(/\s+/);
    const first = parts[0]?.[0] ?? '';
    const last = parts.length > 1 ? parts[parts.length - 1][0] : '';
    return (first + last).toUpperCase().slice(0, 2);
}

const palette = [
    'bg-amber-500',
    'bg-rose-500',
    'bg-fuchsia-500',
    'bg-violet-500',
    'bg-indigo-500',
    'bg-sky-500',
    'bg-emerald-500',
    'bg-teal-500',
    'bg-orange-500',
];

function avatarColor(seed) {
    if (seed === undefined || seed === null) return palette[0];
    const n = Number(seed);
    if (Number.isFinite(n)) return palette[Math.abs(Math.trunc(n)) % palette.length];
    let hash = 0;
    for (const ch of String(seed)) hash = (hash * 31 + ch.charCodeAt(0)) | 0;
    return palette[Math.abs(hash) % palette.length];
}

function dueLabel(date) {
    if (!date) return '';
    try {
        const [y, m, d] = String(date).split('-').map(Number);
        const dt = new Date(y, (m || 1) - 1, d || 1);
        return dt.toLocaleDateString('pt-BR', { day: 'numeric', month: 'short' });
    } catch (_e) {
        return date;
    }
}

function dueClass(card) {
    if (card.completed_at) return 'bg-emerald-100 text-emerald-800';
    if (!card.due_date) return 'bg-slate-100 text-slate-700';
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const [y, m, d] = String(card.due_date).split('-').map(Number);
    const due = new Date(y, (m || 1) - 1, d || 1);
    const diff = (due - today) / 86_400_000;
    if (diff < 0) return 'bg-rose-100 text-rose-800';
    if (diff <= 2) return 'bg-amber-100 text-amber-800';
    return 'bg-slate-100 text-slate-700';
}
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-start gap-3 overflow-x-auto pb-3">
            <div
                v-for="list in localLists"
                :key="list.id"
                :data-list-id="list.id"
                class="flex w-72 shrink-0 flex-col rounded-xl bg-slate-100 p-2 shadow-sm ring-1 ring-slate-200"
            >
                <header class="flex items-start justify-between gap-2 px-1.5 pb-1 pt-1">
                    <div class="min-w-0">
                        <h3 class="truncate text-sm font-semibold text-slate-900">{{ list.name }}</h3>
                        <p
                            v-if="isAdmin"
                            class="mt-0.5 text-[10px] uppercase tracking-wide text-slate-500"
                        >
                            {{ list.visibility }} · drop-in
                            {{ list.allow_company_drop_in ? 'sim' : 'não' }}
                        </p>
                    </div>
                    <button
                        v-if="isAdmin"
                        type="button"
                        class="rounded p-1 text-slate-500 transition hover:bg-slate-200 hover:text-slate-700"
                        title="Ações da lista"
                    >
                        <EllipsisHorizontalIcon class="h-4 w-4" />
                    </button>
                </header>

                <VueDraggable
                    v-model="list.cards"
                    group="kanban-cards"
                    item-key="id"
                    class="flex min-h-[8px] flex-col gap-2 px-0.5"
                    @end="(e) => onCardDragEnd(list.id, e)"
                >
                    <div
                        v-for="card in list.cards"
                        :key="card.id"
                        :data-card-id="card.id"
                        class="group cursor-pointer rounded-lg bg-white px-3 py-2 text-left shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:ring-talents-300"
                        @click="openCard(card)"
                    >
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

                        <p class="text-sm font-medium leading-snug text-slate-900">{{ card.title }}</p>

                        <div class="mt-2 flex items-center justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
                                <span
                                    v-if="card.due_date || card.completed_at"
                                    class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-medium"
                                    :class="dueClass(card)"
                                    :title="card.completed_at ? 'Concluído' : 'Data de entrega'"
                                >
                                    <CheckCircleIcon v-if="card.completed_at" class="h-3.5 w-3.5" />
                                    <CalendarDaysIcon v-else class="h-3.5 w-3.5" />
                                    {{ dueLabel(card.due_date) || (card.completed_at ? 'Concluído' : '') }}
                                </span>

                                <span
                                    v-if="descriptionPresent(card)"
                                    class="inline-flex items-center"
                                    title="Esta tarefa tem descrição"
                                >
                                    <Bars3BottomLeftIcon class="h-3.5 w-3.5" />
                                </span>

                                <span
                                    v-if="card.attachments?.length"
                                    class="inline-flex items-center gap-0.5"
                                    :title="`${card.attachments.length} anexo(s)`"
                                >
                                    <PaperClipIcon class="h-3.5 w-3.5" />
                                    {{ card.attachments.length }}
                                </span>

                                <span
                                    v-if="card.comments?.length"
                                    class="inline-flex items-center gap-0.5"
                                    :title="`${card.comments.length} comentário(s)`"
                                >
                                    <ChatBubbleOvalLeftEllipsisIcon class="h-3.5 w-3.5" />
                                    {{ card.comments.length }}
                                </span>

                                <span
                                    v-if="checklistTotals(card)"
                                    class="inline-flex items-center gap-0.5 rounded-md px-1.5 py-0.5 text-[11px] font-medium"
                                    :class="checklistTotals(card).complete ? 'bg-emerald-100 text-emerald-800' : 'text-slate-600'"
                                    :title="`Checklist ${checklistTotals(card).done}/${checklistTotals(card).total}`"
                                >
                                    <CheckCircleIcon class="h-3.5 w-3.5" />
                                    {{ checklistTotals(card).done }}/{{ checklistTotals(card).total }}
                                </span>
                            </div>

                            <div v-if="card.members?.length" class="flex -space-x-1.5">
                                <span
                                    v-for="m in card.members.slice(0, 3)"
                                    :key="m.id"
                                    :title="m.name"
                                    class="inline-flex h-6 w-6 items-center justify-center rounded-full text-[10px] font-semibold text-white ring-2 ring-white"
                                    :class="avatarColor(m.id)"
                                >
                                    {{ avatarInitials(m.name) }}
                                </span>
                                <span
                                    v-if="card.members.length > 3"
                                    class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-300 text-[10px] font-semibold text-slate-700 ring-2 ring-white"
                                    :title="`+${card.members.length - 3} membros`"
                                >
                                    +{{ card.members.length - 3 }}
                                </span>
                            </div>
                        </div>
                    </div>
                </VueDraggable>

                <div v-if="isAdmin" class="px-0.5 pt-2">
                    <div v-if="composing[list.id]" class="space-y-2">
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
    </div>
</template>
