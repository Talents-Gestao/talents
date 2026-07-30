<script setup>
import HiringFollowUpBoard from '@/Components/HiringFollowUpBoard.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    stages: { type: Array, required: true },
    active_stage: { type: String, required: true },
    stage_counts: { type: Object, required: true },
    processes: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
    company_name: { type: String, required: true },
    can_create: { type: Boolean, default: false },
    can_manage: { type: Boolean, default: true },
    can_delete: { type: Boolean, default: true },
});

const boardRoutes = computed(() => ({
    index: 'client.acompanhamento.index',
    store: 'client.acompanhamento.store',
    update: 'client.acompanhamento.update',
    reorder: 'client.acompanhamento.reorder',
    advance: 'client.acompanhamento.advance',
    retreat: 'client.acompanhamento.retreat',
    destroy: 'client.acompanhamento.destroy',
    comments_store: 'client.acompanhamento.comments.store',
}));
</script>

<template>
    <Head title="Acompanhamento" />

    <ClientLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-talents-900">Acompanhamento</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Lista por fase de {{ company_name }} — arraste para reordenar. O processo operacional continua na Sólides.
                </p>
            </div>
        </template>

        <HiringFollowUpBoard
            :stages="stages"
            :active_stage="active_stage"
            :stage_counts="stage_counts"
            :processes="processes"
            :filters="filters"
            :routes="boardRoutes"
            :can_create="can_create"
            :can_manage="can_manage"
            :can_delete="can_delete"
            :show_company_on_card="false"
        />
    </ClientLayout>
</template>
