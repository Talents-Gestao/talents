<script setup>
import HiringFollowUpBoard from '@/Components/HiringFollowUpBoard.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    stages: { type: Array, required: true },
    active_stage: { type: String, required: true },
    stage_counts: { type: Object, required: true },
    processes: { type: Array, required: true },
    companies: { type: Array, required: true },
    filters: { type: Object, required: true },
});

const boardRoutes = computed(() => ({
    index: 'admin.acompanhamento.index',
    store: 'admin.acompanhamento.store',
    update: 'admin.acompanhamento.update',
    reorder: 'admin.acompanhamento.reorder',
    advance: 'admin.acompanhamento.advance',
    retreat: 'admin.acompanhamento.retreat',
    destroy: 'admin.acompanhamento.destroy',
    comments_store: 'admin.acompanhamento.comments.store',
}));
</script>

<template>
    <Head title="Acompanhamento" />

    <AdminLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-talents-900">Acompanhamento</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Lista por fase — arraste para cima ou para baixo para reordenar. O processo operacional continua na Sólides.
                </p>
            </div>
        </template>

        <HiringFollowUpBoard
            :stages="stages"
            :active_stage="active_stage"
            :stage_counts="stage_counts"
            :processes="processes"
            :companies="companies"
            :filters="filters"
            :routes="boardRoutes"
            can_create
            can_manage
            can_delete
            show_company_filter
            show_company_on_create
            show_talent_bank_link
            show_company_on_card
        />
    </AdminLayout>
</template>
