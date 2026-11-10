<script setup>
import TaskBoardScreen from '@/Components/Tasks/TaskBoardScreen.vue';
import { useAdminPermissions } from '@/composables/useAdminPermissions';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';

/**
 * Tarefas (Admin): tela única com todos os quadros empilhados e recolhíveis. /admin/tarefas/quadros expande
 * os favoritos e o último quadro usado; /admin/tarefas/quadros/{board} expande esse quadro e rola até ele.
 */
const props = defineProps({
    boards: { type: Array, default: () => [] },
    boardPayload: { type: Object, default: null },
    focusBoardId: { type: Number, default: null },
    scrollToFocus: { type: Boolean, default: false },
    companyUsers: { type: Array, default: () => [] },
    teamUsers: { type: Array, default: () => [] },
    companies: { type: Array, default: () => [] },
    visibilityListOptions: { type: Array, default: () => [] },
    visibilityCardOptions: { type: Array, default: () => [] },
    recurrenceOptions: { type: Array, default: () => [] },
});

const { canAdmin } = useAdminPermissions();

const focusName = () =>
    props.scrollToFocus ? props.boards.find((board) => board.id === props.focusBoardId)?.name : null;
</script>

<template>
    <Head :title="focusName() ? `Tarefas — ${focusName()}` : 'Tarefas'" />

    <AdminLayout>
        <TaskBoardScreen
            :is-admin="true"
            :can-edit="canAdmin('tarefas', 'edit')"
            :boards="boards"
            :board-payload="boardPayload"
            :focus-board-id="focusBoardId"
            :scroll-to-focus="scrollToFocus"
            :company-users="companyUsers"
            :team-users="teamUsers"
            :companies="companies"
            :visibility-card-options="visibilityCardOptions"
            :recurrence-options="recurrenceOptions"
            show-route-name="admin.tarefas.quadros.show"
        />
    </AdminLayout>
</template>
