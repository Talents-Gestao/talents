<script setup>
import TaskBoardScreen from '@/Components/Tasks/TaskBoardScreen.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Head } from '@inertiajs/vue3';

/**
 * Tarefas (portal): tela única. /client/tarefas abre no último quadro usado (ou no primeiro favorito,
 * ou no primeiro da lista) e /client/tarefas/{board} abre direto nesse quadro.
 */
defineProps({
    boards: { type: Array, default: () => [] },
    boardPayload: { type: Object, default: null },
    companyUsers: { type: Array, default: () => [] },
});
</script>

<template>
    <Head :title="boardPayload ? `Tarefas — ${boardPayload.name}` : 'Tarefas'" />

    <ClientLayout>
        <div class="p-4">
            <TaskBoardScreen
                :is-admin="false"
                :boards="boards"
                :board-payload="boardPayload"
                :company-users="companyUsers"
                show-route-name="client.tarefas.show"
                empty-message="Nenhum quadro de tarefas compartilhado com você ainda."
            />
        </div>
    </ClientLayout>
</template>
