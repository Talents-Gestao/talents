<script setup>
import TableEmptyRow from '@/Components/TableEmptyRow.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ templates: Object });
</script>

<template>
    <Head title="Mapeamentos" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-900">Mapeamentos</h2>
                <Link
                    :href="route('admin.survey-templates.create')"
                    class="rounded-md bg-talents-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-talents-700"
                >
                    Novo mapeamento
                </Link>
            </div>
        </template>

        <div class="surface-card overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm text-gray-900">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Título</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Seções</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Ativo</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="t in templates.data" :key="t.id">
                        <td class="px-4 py-3">
                            <span>{{ t.title }}</span>
                            <span
                                v-if="t.forked_from_id"
                                class="ml-2 inline-block rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600"
                            >
                                Versão de #{{ t.forked_from_id }}
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ t.sections_count }}</td>
                        <td class="px-4 py-3">{{ t.is_active ? 'Sim' : 'Não' }}</td>
                        <td class="px-4 py-3 text-right space-x-3">
                            <Link :href="route('admin.survey-templates.show', t.id)" class="font-medium text-talents-700 hover:underline">Ver</Link>
                            <Link :href="route('admin.survey-templates.edit', t.id)" class="font-medium text-talents-700 hover:underline">Editar</Link>
                        </td>
                    </tr>
                    <TableEmptyRow v-if="!templates.data.length" :colspan="4" message="Nenhum mapeamento encontrado." />
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
