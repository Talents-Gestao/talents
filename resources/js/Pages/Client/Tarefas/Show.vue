<script setup>
import BoardHeader from '@/Components/Tasks/BoardHeader.vue';
import CardModal from '@/Components/Tasks/CardModal.vue';
import KanbanBoard from '@/Components/Tasks/KanbanBoard.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    boardPayload: Object,
    companyUsers: Array,
});

const modalOpen = ref(false);
const selectedCard = ref(null);

function openCard(card) {
    selectedCard.value = card;
    modalOpen.value = true;
}

function refreshBoard() {
    router.visit(route('client.tarefas.show', props.boardPayload.id), { preserveScroll: true });
}
</script>

<template>
    <Head :title="boardPayload.name" />

    <ClientLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-900">Tarefas</h2>
        </template>

        <div class="space-y-4 p-4">
            <BoardHeader
                :board-payload="boardPayload"
                :is-admin="false"
                :company-users="companyUsers || []"
                @refresh="refreshBoard"
            />

            <KanbanBoard :board-payload="boardPayload" :is-admin="false" @open-card="openCard" @refresh="refreshBoard" />

            <CardModal
                :show="modalOpen"
                :card="selectedCard"
                :board-payload="boardPayload"
                :company-users="companyUsers || []"
                :is-admin="false"
                @close="modalOpen = false"
                @refresh="refreshBoard"
            />
        </div>
    </ClientLayout>
</template>
