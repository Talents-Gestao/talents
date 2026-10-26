<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    opportunity: { type: Object, required: true },
});
</script>

<template>
    <Head :title="`Oportunidade · ${opportunity.company_name}`" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-900">
                {{ opportunity.company_name }} · {{ opportunity.title }}
            </h2>
        </template>

        <div class="space-y-6">
            <Link :href="route('admin.nr1-opportunities.index')" class="text-sm underline">← Oportunidades NR-1</Link>

            <section class="surface-card p-6">
                <p class="text-sm text-gray-600">
                    {{ opportunity.respondents }} respondentes · grupos com menos de
                    {{ opportunity.min_group_size }} respondentes não são exibidos.
                </p>
                <p v-if="opportunity.overall" class="mt-2 font-medium text-gray-900">
                    Geral: {{ opportunity.overall.band_label }} (índice {{ opportunity.overall.score }})
                </p>
            </section>

            <section class="surface-card p-6">
                <h3 class="mb-3 font-medium text-gray-900">Dimensões</h3>
                <ul class="divide-y text-sm">
                    <li v-for="dimension in opportunity.dimensions" :key="dimension.title" class="flex justify-between py-2">
                        <span>{{ dimension.title }}</span>
                        <span>{{ dimension.band_label }} · {{ dimension.score }}</span>
                    </li>
                </ul>
            </section>

            <section v-if="opportunity.sectors.length || opportunity.others" class="surface-card p-6">
                <h3 class="mb-3 font-medium text-gray-900">Setores</h3>
                <ul class="divide-y text-sm">
                    <li
                        v-for="sector in [...opportunity.sectors, ...(opportunity.others ? [opportunity.others] : [])]"
                        :key="sector.name"
                        class="flex justify-between py-2"
                    >
                        <span>{{ sector.name }} ({{ sector.n }})</span>
                        <span>{{ sector.band_label }} · {{ sector.score }}</span>
                    </li>
                </ul>
            </section>
        </div>
    </AdminLayout>
</template>
