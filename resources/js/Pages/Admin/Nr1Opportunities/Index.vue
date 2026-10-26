<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    surveys: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Oportunidades NR-1" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-900">Oportunidades NR-1</h2>
        </template>

        <div class="surface-card p-6">
            <p class="mb-4 text-sm text-gray-600">
                Resultados agregados de campanhas liberadas. Respostas individuais nunca aparecem aqui.
            </p>

            <p v-if="!surveys.length" class="text-sm text-gray-600">Nenhuma campanha liberada.</p>

            <ul v-else class="divide-y">
                <li v-for="survey in surveys" :key="survey.id" class="flex items-center justify-between gap-4 py-3">
                    <div class="min-w-0">
                        <p class="font-medium text-gray-900">{{ survey.company_name }} · {{ survey.title }}</p>
                        <p class="text-sm text-gray-600">
                            {{ survey.starts_at }} a {{ survey.ends_at }} · liberada em {{ survey.released_at }}
                        </p>
                    </div>
                    <Link :href="route('admin.nr1-opportunities.show', survey.id)" class="shrink-0 text-sm underline">
                        Ver resultado
                    </Link>
                </li>
            </ul>
        </div>
    </AdminLayout>
</template>
