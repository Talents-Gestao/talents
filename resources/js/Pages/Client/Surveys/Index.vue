<script setup>
import TableEmptyRow from '@/Components/TableEmptyRow.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ surveys: Object });
</script>

<template>
    <Head title="Pesquisas NR1" />

    <ClientLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-talents-900">Pesquisas NR1</h2>
                <Link
                    :href="route('client.surveys.create')"
                    class="rounded-md bg-talents-700 px-4 py-2 text-sm font-semibold text-white hover:bg-talents-800"
                >
                    Nova campanha
                </Link>
            </div>
        </template>

        <div class="surface-card overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Título</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Template</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="s in surveys.data" :key="s.id">
                        <td class="px-4 py-3">{{ s.title }}</td>
                        <td class="px-4 py-3">{{ s.template?.title }}</td>
                        <td class="px-4 py-3">{{ s.status }}</td>
                        <td class="px-4 py-3 text-right space-x-3">
                            <Link :href="route('client.surveys.show', s.id)" class="text-talents-700 hover:underline">Abrir</Link>
                            <Link :href="route('client.surveys.results', s.id)" class="text-talents-700 hover:underline">Resultados</Link>
                        </td>
                    </tr>
                    <TableEmptyRow v-if="!surveys.data.length" :colspan="4" message="Nenhuma campanha encontrada." />
                </tbody>
            </table>
        </div>
    </ClientLayout>
</template>
