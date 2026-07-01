<script setup>
import TableEmptyRow from '@/Components/TableEmptyRow.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ plans: Object });
</script>

<template>
    <Head title="Planos" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-900">Planos</h2>
                <Link
                    :href="route('admin.plans.create')"
                    class="rounded-md bg-talents-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-talents-700"
                >
                    Novo plano
                </Link>
            </div>
        </template>

        <div class="surface-card overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm text-gray-900">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Nome</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Preço/mês (R$)</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Módulos</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="p in plans.data" :key="p.id">
                        <td class="px-4 py-3">{{ p.name }}</td>
                        <td class="px-4 py-3">{{ (p.price_monthly_cents / 100).toFixed(2) }}</td>
                        <td class="px-4 py-3">
                            <span v-for="m in p.modules" :key="m.id" class="mr-2 rounded bg-talents-100 px-2 py-0.5 text-xs text-talents-900">{{ m.name }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="route('admin.plans.edit', p.id)" class="font-medium text-talents-700 hover:underline">Editar</Link>
                        </td>
                    </tr>
                    <TableEmptyRow v-if="!plans.data.length" :colspan="4" message="Nenhum plano encontrado." />
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
