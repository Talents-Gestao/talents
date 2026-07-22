<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    notices: Object,
    companies: Array,
    filters: Object,
});
</script>

<template>
    <Head title="Avisos às empresas" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-talents-900">Avisos às empresas</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Comunicados manuais e avisos automáticos do calendário estratégico.
                    </p>
                </div>
                <Link
                    :href="route('admin.notices.create')"
                    class="rounded-lg bg-talents-700 px-4 py-2 text-sm font-semibold text-white hover:bg-talents-800"
                >
                    Novo aviso
                </Link>
            </div>
        </template>

        <div class="surface-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Empresa</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Título</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Publicado</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Origem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="notice in notices.data" :key="notice.id">
                            <td class="px-4 py-3 text-slate-700">{{ notice.company?.name ?? '—' }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ notice.title }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ notice.published_at ? new Date(notice.published_at).toLocaleString('pt-BR') : '—' }}
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ notice.source_type === 'strategic_calendar_item' ? 'Calendário' : 'Manual' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="!notices.data?.length" class="px-4 py-8 text-center text-sm text-slate-500">
                Nenhum aviso publicado.
            </p>
        </div>
    </AdminLayout>
</template>
