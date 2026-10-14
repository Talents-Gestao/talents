<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import PontoConfigPanel from '@/Components/Admin/TimeClock/PontoConfigPanel.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    company: { type: Object, required: true },
});

const pageTitle = computed(() => `Configuração RHID — ${props.company.name}`);
</script>

<template>
    <Head :title="pageTitle" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.companies.show', { company: company.id, tab: 'ponto' })"
                back-label="Voltar à empresa"
                title="Integração RHID (Control iD)"
                :subtitle="company.name"
            />
        </template>

        <div class="mx-auto max-w-4xl">
            <PontoConfigPanel :company="company" />
        </div>
    </AdminLayout>
</template>
