<script setup>
import CardModal from '@/Components/Tasks/CardModal.vue';
import KanbanBoard from '@/Components/Tasks/KanbanBoard.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    boardPayload: Object,
    activity: Array,
    companyUsers: Array,
    visibilityListOptions: Array,
    visibilityCardOptions: Array,
});

const modalOpen = ref(false);
const selectedCard = ref(null);

function openCard(card) {
    selectedCard.value = card;
    modalOpen.value = true;
}

function refreshBoard() {
    router.visit(route('admin.tarefas.quadros.show', props.boardPayload.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="boardPayload.name" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-xl font-semibold text-gray-900">{{ boardPayload.name }}</h2>
                <div class="flex gap-2 text-sm">
                    <a
                        :href="route('admin.tarefas.quadros.index')"
                        class="text-talents-700 underline"
                    >
                        Quadros
                    </a>
                </div>
            </div>
        </template>

        <div class="space-y-6 p-4">
            <KanbanBoard
                :board-payload="boardPayload"
                :is-admin="true"
                @open-card="openCard"
                @refresh="refreshBoard"
            />

            <div v-if="activity?.length" class="surface-card p-4">
                <h3 class="font-semibold text-slate-800">Atividade recente</h3>
                <ul class="mt-2 max-h-48 space-y-1 overflow-y-auto text-xs text-slate-600">
                    <li v-for="row in activity" :key="row.id">
                        <span class="font-medium">{{ row.action }}</span>
                        <span v-if="row.actor"> · {{ row.actor.name }}</span>
                        <span class="text-slate-400"> · {{ row.created_at }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <CardModal
            :show="modalOpen"
            :card="selectedCard"
            :board-payload="boardPayload"
            :company-users="companyUsers || []"
            :is-admin="true"
            :visibility-card-options="visibilityCardOptions || []"
            @close="modalOpen = false"
            @refresh="refreshBoard"
        />
    </AdminLayout>
</template>
