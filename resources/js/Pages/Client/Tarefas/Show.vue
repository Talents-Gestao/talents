<script setup>
import BoardHeader from '@/Components/Tasks/BoardHeader.vue';
import CardModal from '@/Components/Tasks/CardModal.vue';
import KanbanBoard from '@/Components/Tasks/KanbanBoard.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    boardPayload: Object,
    companyUsers: Array,
});

const modalOpen = ref(false);
const selectedCard = ref(null);
const viewMode = ref('kanban');

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
    router.visit(route('client.tarefas.show', props.boardPayload.id), { preserveScroll: true });
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

    <ClientLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-900">Tarefas</h2>
        </template>

        <div class="space-y-4 p-4">
            <section
                class="rounded-2xl bg-gradient-to-r from-slate-900 via-violet-900 to-slate-900 px-5 py-4 text-white shadow-sm"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-white/70">Quadro ativo</p>
                        <h3 class="mt-1 text-xl font-semibold">{{ boardPayload.name }}</h3>
                    </div>
                    <a
                        :href="route('client.tarefas.index')"
                        class="rounded-lg bg-white/10 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-white/20"
                    >
                        Voltar para quadros
                    </a>
                </div>
            </section>

            <BoardHeader
                :board-payload="boardPayload"
                :is-admin="false"
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
                :is-admin="false"
                @open-card="openCard"
                @refresh="refreshBoard"
            />

            <div v-else class="overflow-x-auto rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-600">
                            <th class="px-2 py-2">Tarefa</th>
                            <th class="px-2 py-2">Coluna</th>
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
                            <td class="px-2 py-2 text-slate-600">{{ formatDate(card.due_date) }}</td>
                            <td class="px-2 py-2 text-slate-600">
                                {{ card.completed_at ? 'Concluída' : 'Aberta' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <CardModal
                :show="modalOpen"
                :card="selectedCard"
                :board-payload="boardPayload"
                :company-users="companyUsers || []"
                :is-admin="false"
                @close="modalOpen = false"
                @refresh="refreshBoard"
                @sync-card="syncSelectedCard"
            />
        </div>
    </ClientLayout>
</template>
