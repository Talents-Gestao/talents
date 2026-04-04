<script setup>
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ complaints: Object });

const statusLabel = (s) => {
    const map = {
        new: 'Nova',
        under_review: 'Em análise',
        resolved: 'Resolvida',
        archived: 'Arquivada',
    };
    return map[s] || s;
};

const categoryLabel = (c) => {
    const map = {
        assedio_moral: 'Assédio moral',
        assedio_sexual: 'Assédio sexual',
        discriminacao: 'Discriminação',
        corrupcao: 'Corrupção ou fraude',
        seguranca: 'Segurança',
        outros: 'Outros',
    };
    return map[c] || c;
};
</script>

<template>
    <Head title="Canal de denúncias" />

    <ClientLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-talents-900">Denúncias</h2>
        </template>

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Protocolo</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Tipo</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Setor</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Status</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Data</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="c in complaints.data" :key="c.id">
                        <td class="px-4 py-3 font-mono text-xs">{{ c.protocol }}</td>
                        <td class="px-4 py-3">{{ categoryLabel(c.category) }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ c.department_name || '—' }}</td>
                        <td class="px-4 py-3">{{ statusLabel(c.status) }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ c.created_at }}</td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="route('client.complaints.show', c.id)" class="font-medium text-talents-700 hover:underline">Abrir</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!complaints.data?.length" class="px-4 py-8 text-center text-sm text-gray-500">Nenhuma denúncia registrada.</p>
        </div>
    </ClientLayout>
</template>
