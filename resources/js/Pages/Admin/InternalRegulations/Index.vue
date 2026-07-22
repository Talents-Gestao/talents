<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { MagnifyingGlassIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { ref } from 'vue';

const props = defineProps({
    regulations: { type: Object, required: true },
    companies: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const searchQ = ref(props.filters.q ?? '');
const companyId = ref(props.filters.company_id ? String(props.filters.company_id) : '');

const fieldClass =
    'rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70';

const applyFilters = () => {
    router.get(
        route('admin.regulamento-interno.index'),
        {
            company_id: companyId.value || undefined,
            q: searchQ.value || undefined,
        },
        { preserveState: true, replace: true },
    );
};

const createHref = () =>
    route('admin.regulamento-interno.create', companyId.value ? { company_id: companyId.value } : {});

const remove = (id) => {
    if (confirm('Remover este regulamento interno?')) {
        router.delete(route('admin.regulamento-interno.destroy', id));
    }
};
</script>

<template>
    <Head title="Regulamento interno" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.dashboard')"
                back-label="Dashboard"
                title="Regulamento interno"
                subtitle="Documentos por empresa com editor de texto formatado"
            >
                <template #trailing>
                    <Link :href="createHref()">
                        <PrimaryButton type="button">Novo regulamento</PrimaryButton>
                    </Link>
                </template>
            </FormPageHeader>
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
        >
            {{ $page.props.flash.success }}
        </div>

        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="relative min-w-0 flex-1">
                <MagnifyingGlassIcon
                    class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                />
                <input
                    v-model="searchQ"
                    type="search"
                    :class="fieldClass + ' w-full pl-9'"
                    placeholder="Buscar por título ou empresa…"
                    @keydown.enter.prevent="applyFilters"
                >
            </div>
            <select v-model="companyId" :class="fieldClass" @change="applyFilters">
                <option value="">Todas as empresas</option>
                <option v-for="c in companies" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
            </select>
            <PrimaryButton type="button" @click="applyFilters">Filtrar</PrimaryButton>
        </div>

        <div class="surface-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Título</th>
                            <th class="px-4 py-3">Empresa</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3">Atualizado</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 bg-white">
                        <tr v-for="row in regulations.data" :key="row.id" class="hover:bg-slate-50/70">
                            <td class="px-4 py-3 font-medium text-slate-900">{{ row.title }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ row.company?.name || '—' }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1"
                                    :class="
                                        row.is_published
                                            ? 'bg-emerald-50 text-emerald-800 ring-emerald-200/80'
                                            : 'bg-slate-100 text-slate-600 ring-slate-200/80'
                                    "
                                >
                                    {{ row.is_published ? 'Publicado' : 'Rascunho' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-500">
                                {{
                                    row.updated_at
                                        ? new Date(row.updated_at).toLocaleString('pt-BR', {
                                              dateStyle: 'short',
                                              timeStyle: 'short',
                                          })
                                        : '—'
                                }}
                            </td>
                            <td class="space-x-3 px-4 py-3 text-right">
                                <Link
                                    :href="route('admin.regulamento-interno.edit', row.id)"
                                    class="inline-flex items-center gap-1 font-medium text-talents-700 hover:underline"
                                >
                                    <PencilSquareIcon class="h-4 w-4" aria-hidden="true" />
                                    Editar
                                </Link>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 font-medium text-rose-600 hover:underline"
                                    @click="remove(row.id)"
                                >
                                    <TrashIcon class="h-4 w-4" aria-hidden="true" />
                                    Remover
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!regulations.data?.length">
                            <td colspan="5" class="px-4 py-10 text-center text-slate-500">
                                Nenhum regulamento encontrado. Crie o primeiro documento.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div
            v-if="regulations.links && regulations.links.length > 3"
            class="mt-4 flex flex-wrap justify-end gap-2"
        >
            <template v-for="(link, i) in regulations.links" :key="i">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="rounded px-2 py-1 text-sm"
                    :class="link.active ? 'bg-talents-600 text-white' : 'text-talents-700 hover:bg-talents-50'"
                    preserve-scroll
                    v-html="link.label"
                />
                <span v-else class="rounded px-2 py-1 text-sm text-slate-400" v-html="link.label" />
            </template>
        </div>
    </AdminLayout>
</template>
