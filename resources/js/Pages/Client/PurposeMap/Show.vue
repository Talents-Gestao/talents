<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import PurposeMapDashboardPanel from '@/Components/PurposeMap/DashboardPanel.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    campaign: { type: Object, required: true },
    publicUrl: { type: String, required: true },
    questionsUrl: { type: String, required: true },
    sectorFilter: { type: String, default: 'all' },
    dashboard: { type: Object, required: true },
    themesPending: { type: Boolean, default: false },
    product: { type: Object, required: true },
    actionUrls: { type: Object, required: true },
});
</script>

<template>
    <Head :title="campaign.title" />

    <ClientLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('client.voz-do-time.index')"
                back-label="Voz do Time"
                :title="product.name"
                :subtitle="product.subtitle"
                :prefer-history="true"
            >
                <template #trailing>
                    <a
                        :href="questionsUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="rounded-md bg-talents-700 px-3 py-2 text-sm font-semibold text-white hover:bg-talents-800"
                    >
                        Ver perguntas
                    </a>
                </template>
            </FormPageHeader>
        </template>

        <PurposeMapDashboardPanel
            :campaign="campaign"
            :public-url="publicUrl"
            :sector-filter="sectorFilter"
            :dashboard="dashboard"
            :themes-pending="themesPending"
            :action-urls="actionUrls"
        />
    </ClientLayout>
</template>
