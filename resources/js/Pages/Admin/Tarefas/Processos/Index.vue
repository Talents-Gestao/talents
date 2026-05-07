<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    templates: Object,
    filters: Object,
});

function destroy(id) {
    if (!confirm('Remover este modelo de processo?')) return;
    router.delete(route('admin.tarefas.processos.destroy', id));
}
</script>

<template>
    <Head title="Tarefas — Processos" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-xl font-semibold text-gray-900">Modelos de processo</h2>
                <Link
                    :href="route('admin.tarefas.processos.create')"
                    class="inline-flex rounded-md bg-talents-600 px-3 py-2 text-sm font-medium text-white"
                >
                    Novo modelo
                </Link>
            </div>
        </template>

        <div class="surface-card overflow-x-auto p-4">
            <table class="min-w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-600">
                        <th class="py-2 pr-4">Nome</th>
                        <th class="py-2 pr-4">Slug</th>
                        <th class="py-2 pr-4">Listas</th>
                        <th class="py-2 pr-4">Quadros</th>
                        <th class="py-2">—</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="t in templates.data" :key="t.id" class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-medium text-slate-900">{{ t.name }}</td>
                        <td class="py-2 pr-4 text-slate-600">{{ t.slug }}</td>
                        <td class="py-2 pr-4">{{ t.lists_count }}</td>
                        <td class="py-2 pr-4">{{ t.boards_count }}</td>
                        <td class="py-2 space-x-2">
                            <Link
                                :href="route('admin.tarefas.processos.edit', t.id)"
                                class="text-talents-700 underline"
                            >
                                Editar
                            </Link>
                            <button type="button" class="text-red-600 underline" @click="destroy(t.id)">
                                Remover
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
