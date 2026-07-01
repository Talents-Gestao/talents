<script setup>
import TableEmptyRow from '@/Components/TableEmptyRow.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ templates: Object });
</script>

<template>
    <Head title="Templates — Direcionamento Estratégico" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-900">Templates — Pesquisa de satisfação</h2>
                    <p class="mt-1 text-sm text-gray-600">Formulários editáveis da etapa 02, vinculados às empresas pelo cadastro.</p>
                </div>
                <Link
                    :href="route('admin.methodology-templates.create')"
                    class="rounded-md bg-talents-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-talents-700"
                >
                    Novo template
                </Link>
            </div>
        </template>

        <div class="surface-card overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm text-gray-900">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Título</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Seções</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Empresas</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Ativo</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="t in templates.data" :key="t.id">
                        <td class="px-4 py-3">{{ t.title }}</td>
                        <td class="px-4 py-3">{{ t.sections_count }}</td>
                        <td class="px-4 py-3">{{ t.companies_count }}</td>
                        <td class="px-4 py-3">{{ t.is_active ? 'Sim' : 'Não' }}</td>
                        <td class="px-4 py-3 text-right space-x-3">
                            <Link :href="route('admin.methodology-templates.show', t.id)" class="font-medium text-talents-700 hover:underline">Ver</Link>
                            <Link :href="route('admin.methodology-templates.edit', t.id)" class="font-medium text-talents-700 hover:underline">Editar</Link>
                        </td>
                    </tr>
                    <TableEmptyRow v-if="!templates.data.length" :colspan="5" message="Nenhum template encontrado." />
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
