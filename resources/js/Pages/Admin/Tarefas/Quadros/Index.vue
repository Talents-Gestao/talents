<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    boards: Object,
    companies: Array,
    filters: Object,
});
</script>

<template>
    <Head title="Tarefas — Quadros" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-xl font-semibold text-gray-900">Quadros</h2>
                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="route('admin.tarefas.processos.index')"
                        class="inline-flex items-center rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-800"
                    >
                        Modelos de processo
                    </Link>
                    <Link
                        :href="route('admin.tarefas.quadros.ativar')"
                        class="inline-flex items-center rounded-md bg-slate-100 px-3 py-2 text-sm font-medium text-slate-800"
                    >
                        Ativar processo
                    </Link>
                    <Link
                        :href="route('admin.tarefas.quadros.create')"
                        class="inline-flex items-center rounded-md bg-talents-600 px-3 py-2 text-sm font-medium text-white"
                    >
                        Novo quadro interno
                    </Link>
                </div>
            </div>
        </template>

        <div class="surface-card overflow-x-auto p-4">
            <form class="mb-4 flex flex-wrap gap-2 text-sm" method="get" :action="route('admin.tarefas.quadros.index')">
                <select
                    name="company_id"
                    class="rounded border border-slate-300 px-2 py-1"
                    :value="filters?.company_id || ''"
                >
                    <option value="">Todas as empresas</option>
                    <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <select name="scope" class="rounded border border-slate-300 px-2 py-1" :value="filters?.scope || ''">
                    <option value="">Todos</option>
                    <option value="internal">Internos Talents</option>
                    <option value="company">Por empresa</option>
                </select>
                <PrimaryButton type="submit">Filtrar</PrimaryButton>
            </form>

            <table class="min-w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-600">
                        <th class="py-2 pr-4">Nome</th>
                        <th class="py-2 pr-4">Empresa</th>
                        <th class="py-2">—</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="b in boards.data" :key="b.id" class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-medium text-slate-900">{{ b.name }}</td>
                        <td class="py-2 pr-4 text-slate-600">{{ b.company?.name || '— Talents —' }}</td>
                        <td class="py-2">
                            <Link
                                :href="route('admin.tarefas.quadros.show', b.id)"
                                class="text-talents-700 underline"
                            >
                                Abrir
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
