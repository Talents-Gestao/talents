<script setup>
import SurveyStatusBadge from '@/Components/SurveyStatusBadge.vue';
import TableEmptyRow from '@/Components/TableEmptyRow.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ surveys: Object });
</script>

<template>
    <Head title="Pesquisas de satisfação" />

    <ClientLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-talents-900">Pesquisas de satisfação</h2>
                <Link
                    :href="route('client.metodologia.pesquisa-satisfacao.create')"
                    class="rounded-md bg-talents-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-talents-700"
                >
                    Nova pesquisa
                </Link>
            </div>
        </template>

        <div class="surface-card overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm text-gray-900">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Título</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Template</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Status</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Respostas</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="s in surveys.data" :key="s.id">
                        <td class="px-4 py-3 font-medium">{{ s.title }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ s.template?.title }}</td>
                        <td class="px-4 py-3">
                            <SurveyStatusBadge :status="s.status" />
                        </td>
                        <td class="px-4 py-3">{{ s.completed_responses_count }}</td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <Link :href="route('client.metodologia.pesquisa-satisfacao.show', s.id)" class="font-medium text-talents-700 hover:underline">Ver</Link>
                            <Link :href="route('client.metodologia.pesquisa-satisfacao.results', s.id)" class="font-medium text-talents-700 hover:underline">Resultados</Link>
                        </td>
                    </tr>
                    <TableEmptyRow v-if="!surveys.data.length" :colspan="5" message="Nenhuma pesquisa encontrada." />
                </tbody>
            </table>
        </div>
        <p class="mt-4">
            <Link :href="route('client.metodologia.index')" class="text-sm text-talents-700 hover:underline">← Direcionamento Estratégico</Link>
        </p>
    </ClientLayout>
</template>
