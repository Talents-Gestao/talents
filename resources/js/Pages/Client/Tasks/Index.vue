<script setup>
import TaskBoardScreen from '@/Components/Tasks/TaskBoardScreen.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Head } from '@inertiajs/vue3';

/**
 * Tarefas (portal): tela única com todos os quadros visíveis empilhados e recolhíveis. /client/tarefas expande
 * os favoritos e o último quadro usado; /client/tarefas/{board} expande esse quadro e rola até ele.
 */
const props = defineProps({
    boards: { type: Array, default: () => [] },
    boardPayload: { type: Object, default: null },
    focusBoardId: { type: Number, default: null },
    scrollToFocus: { type: Boolean, default: false },
    companyUsers: { type: Array, default: () => [] },
});

const focusName = () =>
    props.scrollToFocus ? props.boards.find((board) => board.id === props.focusBoardId)?.name : null;
</script>

<template>
    <Head :title="focusName() ? `Tarefas — ${focusName()}` : 'Tarefas'" />

    <ClientLayout>
        <div class="p-4">
            <TaskBoardScreen
                :is-admin="false"
                :boards="boards"
                :board-payload="boardPayload"
                :focus-board-id="focusBoardId"
                :scroll-to-focus="scrollToFocus"
                :company-users="companyUsers"
                show-route-name="client.tarefas.show"
                empty-message="Nenhum quadro de tarefas compartilhado com você ainda."
            />
        </div>
    </ClientLayout>
</template>
