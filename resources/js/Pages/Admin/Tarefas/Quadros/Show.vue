<script setup>
import BoardHeader from '@/Components/Tasks/BoardHeader.vue';
import CardModal from '@/Components/Tasks/CardModal.vue';
import KanbanBoard from '@/Components/Tasks/KanbanBoard.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    boardPayload: Object,
    companyUsers: Array,
    companies: Array,
    visibilityListOptions: Array,
    visibilityCardOptions: Array,
});

const modalOpen = ref(false);
const selectedCard = ref(null);
const viewMode = ref('kanban');

function openCard(card) {
    selectedCard.value = card;
    modalOpen.value = true;
}

function refreshBoard() {
    router.visit(route('admin.tarefas.quadros.show', props.boardPayload.id), {
        preserveScroll: true,
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
    if (!value) return '—';
    const dt = new Date(value);
    if (Number.isNaN(dt.getTime())) return value;
    return dt.toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}
</script>

<template>
    <Head :title="boardPayload.name" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-900">Tarefas</h2>
        </template>

        <div class="space-y-4 p-4">
            <BoardHeader
                :board-payload="boardPayload"
                :is-admin="true"
                :company-users="companyUsers || []"
                @refresh="refreshBoard"
            />

            <div class="flex items-center justify-end gap-2">
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
                :board-payload="boardPayload"
                :is-admin="true"
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
                            <td class="px-2 py-2 text-slate-600">{{ formatDate(card.due_date) }}</td>
                            <td class="px-2 py-2 text-slate-600">
                                {{ card.completed_at ? 'Concluída' : 'Aberta' }}
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
            :companies="companies || []"
            :is-admin="true"
            :visibility-card-options="visibilityCardOptions || []"
            @close="modalOpen = false"
            @refresh="refreshBoard"
        />
    </AdminLayout>
</template>
