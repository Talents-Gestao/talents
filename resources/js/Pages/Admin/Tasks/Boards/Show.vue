<script setup>
import BoardHeader from '@/Components/Tasks/BoardHeader.vue';
import CardModal from '@/Components/Tasks/CardModal.vue';
import KanbanBoard from '@/Components/Tasks/KanbanBoard.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatDateNumeric, formatRelativeDate } from '@/utils/dateOnly';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    boardPayload: Object,
    companyUsers: Array,
    teamUsers: Array,
    companies: Array,
    visibilityListOptions: Array,
    visibilityCardOptions: Array,
    recurrenceOptions: Array,
});

const modalOpen = ref(false);
const selectedCard = ref(null);
const viewMode = ref('kanban');

const boardKanbanKey = computed(() =>
    (props.boardPayload?.lists || [])
        .flatMap((list) => list.cards || [])
        .map((card) => `${card.id}:${card.checklist_total ?? 0}:${card.checklist_done ?? 0}`)
        .join('|'),
);

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

function refreshBoard() {
    const params = props.boardPayload?.show_archived ? { ver_arquivados: 1 } : {};
    router.get(route('admin.tarefas.quadros.show', props.boardPayload.id), params, {
        preserveScroll: true,
    });
}

const showArchived = computed(() => Boolean(props.boardPayload?.show_archived));

function toggleShowArchived() {
    router.get(
        route('admin.tarefas.quadros.show', props.boardPayload.id),
        showArchived.value ? {} : { ver_arquivados: 1 },
        { preserveScroll: true },
    );
}

function deleteListCard(card, event) {
    event?.stopPropagation?.();
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

function formatDate(value) {
    return formatRelativeDate(value);
}

function formatDateTitle(value) {
    if (!value) return undefined;
    const relative = formatRelativeDate(value);
    const absolute = formatDateNumeric(value);
    if (!absolute || relative === absolute) return absolute;
    return `${relative} (${absolute})`;
}
</script>

<template>
    <Head :title="boardPayload.name" />

    <AdminLayout>
        <template #header>
            <div>
                <p class="text-sm text-gray-500">
                    <Link :href="route('admin.tarefas.quadros.index')" class="text-talents-700 hover:underline">
                        Quadros
                    </Link>
                    / {{ boardPayload.name }}
                </p>
                <h2 class="text-xl font-semibold text-gray-900">{{ boardPayload.name }}</h2>
            </div>
        </template>

        <div class="space-y-4 p-4">
            <BoardHeader
                :board-payload="boardPayload"
                :is-admin="true"
                :company-users="companyUsers || []"
                :team-users="teamUsers || []"
                @refresh="refreshBoard"
            />

            <div class="flex items-center justify-end gap-2">
                <button
                    v-if="viewMode === 'kanban'"
                    type="button"
                    class="rounded-md px-3 py-1.5 text-sm font-medium ring-1 ring-slate-300"
                    :class="showArchived ? 'bg-amber-100 text-amber-900 ring-amber-300' : 'bg-white text-slate-700'"
                    @click="toggleShowArchived"
                >
                    {{ showArchived ? 'Ocultar arquivados' : 'Ver arquivados' }}
                </button>
                <button
                    type="button"
                    class="rounded-md px-3 py-1.5 text-sm font-medium ring-1 ring-slate-300"
                    :class="viewMode === 'kanban' ? 'bg-talents-600 text-white ring-talents-600' : 'bg-white text-slate-700'"
                    @click="viewMode = 'kanban'"
                >
                    Kanban
                </button>
                <button
                    type="button"
                    class="rounded-md px-3 py-1.5 text-sm font-medium ring-1 ring-slate-300"
                    :class="viewMode === 'lista' ? 'bg-talents-600 text-white ring-talents-600' : 'bg-white text-slate-700'"
                    @click="viewMode = 'lista'"
                >
                    Lista
                </button>
            </div>

            <KanbanBoard
                v-if="viewMode === 'kanban'"
                :key="boardKanbanKey"
                :board-payload="boardPayload"
                :is-admin="true"
                :companies="companies || []"
                @open-card="openCard"
                @refresh="refreshBoard"
            />

            <div v-else class="overflow-x-auto rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-600">
                            <th class="px-2 py-2">Tarefa</th>
                            <th class="px-2 py-2">Coluna</th>
                            <th class="px-2 py-2">Cliente</th>
                            <th class="px-2 py-2">Vencimento</th>
                            <th class="px-2 py-2">Status</th>
                            <th class="w-24 px-2 py-2 text-right">Ações</th>
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
                            <td class="px-2 py-2 text-slate-600">{{ card.company?.name || '—' }}</td>
                            <td class="px-2 py-2 text-slate-600" :title="formatDateTitle(card.due_date)">
                                {{ formatDate(card.due_date) }}
                            </td>
                            <td class="px-2 py-2 text-slate-600">
                                {{ card.completed_at ? 'Concluída' : 'Aberta' }}
                            </td>
                            <td class="px-2 py-2 text-right">
                                <button
                                    type="button"
                                    class="text-xs font-medium text-rose-600 hover:underline"
                                    @click="deleteListCard(card, $event)"
                                >
                                    Excluir
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <CardModal
            :show="modalOpen"
            :card="selectedCard"
            :board-payload="boardPayload"
            :company-users="companyUsers || []"
            :team-users="teamUsers || []"
            :companies="companies || []"
            :is-admin="true"
            :visibility-card-options="visibilityCardOptions || []"
            :recurrence-options="recurrenceOptions || []"
            @close="modalOpen = false"
            @refresh="refreshBoard"
            @sync-card="syncSelectedCard"
        />
    </AdminLayout>
</template>
