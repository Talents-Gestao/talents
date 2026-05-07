<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { router } from '@inertiajs/vue3';
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

function onCardDragEnd(fromListId, evt) {
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
                reload();
            },
        },
    );
}

const newCardTitles = ref({});
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
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap gap-3 overflow-x-auto pb-2">
            <div
                v-for="list in localLists"
                :key="list.id"
                :data-list-id="list.id"
                class="surface-card flex w-72 shrink-0 flex-col gap-2 p-3 shadow-sm"
            >
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h3 class="font-semibold text-slate-900">{{ list.name }}</h3>
                        <p v-if="isAdmin" class="text-[10px] uppercase tracking-wide text-slate-500">
                            {{ list.visibility }} · drop-in
                            {{ list.allow_company_drop_in ? 'sim' : 'não' }}
                        </p>
                    </div>
                </div>

                <VueDraggable
                    v-model="list.cards"
                    group="kanban-cards"
                    item-key="id"
                    class="flex min-h-[120px] flex-col gap-2"
                    @end="(e) => onCardDragEnd(list.id, e)"
                >
                    <div
                        v-for="card in list.cards"
                        :key="card.id"
                        :data-card-id="card.id"
                        class="cursor-pointer rounded-lg border border-slate-200 bg-white p-2 text-left shadow-sm transition hover:border-talents-300"
                        @click="openCard(card)"
                    >
                        <div class="flex flex-wrap gap-1">
                            <span
                                v-for="lb in card.labels"
                                :key="lb.id"
                                class="inline-block h-2 w-6 rounded"
                                :style="{ backgroundColor: lb.color }"
                                :title="lb.name || lb.color"
                            />
                        </div>
                        <p class="mt-1 text-sm font-medium text-slate-900">{{ card.title }}</p>
                        <p v-if="card.due_date" class="mt-1 text-xs text-slate-500">
                            Vence {{ card.due_date }}
                        </p>
                    </div>
                </VueDraggable>

                <div v-if="isAdmin" class="border-t border-slate-100 pt-2">
                    <TextInput
                        v-model="newCardTitles[list.id]"
                        class="block w-full text-sm"
                        placeholder="Novo cartão…"
                        @keyup.enter="submitNewCard(list)"
                    />
                    <PrimaryButton type="button" class="mt-2 w-full py-1 text-xs" @click="submitNewCard(list)">
                        Adicionar cartão
                    </PrimaryButton>
                </div>
            </div>

            <div v-if="isAdmin" class="surface-card w-72 shrink-0 space-y-2 p-3">
                <h3 class="font-semibold text-slate-900">Nova lista</h3>
                <TextInput v-model="newListName" placeholder="Nome da lista" class="block w-full text-sm" />
                <PrimaryButton type="button" class="w-full py-1 text-sm" @click="submitNewList">
                    Adicionar lista
                </PrimaryButton>
            </div>
        </div>

        <div v-if="isAdmin && labels.length" class="text-xs text-slate-500">
            Etiquetas no quadro: {{ labels.map((l) => l.name || l.color).join(' · ') }}
        </div>
    </div>
</template>
