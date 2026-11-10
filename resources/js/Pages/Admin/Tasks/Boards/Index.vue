<script setup>
import TaskBoardScreen from '@/Components/Tasks/TaskBoardScreen.vue';
import { useAdminPermissions } from '@/composables/useAdminPermissions';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';

/**
 * Tarefas (Admin): tela única. /admin/tarefas/quadros abre no último quadro usado (ou no primeiro favorito,
 * ou no primeiro da lista) e /admin/tarefas/quadros/{board} abre direto nesse quadro.
 */
defineProps({
    boards: { type: Array, default: () => [] },
    boardPayload: { type: Object, default: null },
    companyUsers: { type: Array, default: () => [] },
    teamUsers: { type: Array, default: () => [] },
    companies: { type: Array, default: () => [] },
    visibilityListOptions: { type: Array, default: () => [] },
    visibilityCardOptions: { type: Array, default: () => [] },
    recurrenceOptions: { type: Array, default: () => [] },
});

const { canAdmin } = useAdminPermissions();
</script>

<template>
    <Head :title="boardPayload ? `Tarefas — ${boardPayload.name}` : 'Tarefas'" />

    <AdminLayout>
        <TaskBoardScreen
            :is-admin="true"
            :can-edit="canAdmin('tarefas', 'edit')"
            :boards="boards"
            :board-payload="boardPayload"
            :company-users="companyUsers"
            :team-users="teamUsers"
            :companies="companies"
            :visibility-card-options="visibilityCardOptions"
            :recurrence-options="recurrenceOptions"
            show-route-name="admin.tarefas.quadros.show"
        />
    </AdminLayout>
</template>
