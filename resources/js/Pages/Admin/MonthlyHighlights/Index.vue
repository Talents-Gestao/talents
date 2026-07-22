<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { MagnifyingGlassIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { ref } from 'vue';

const props = defineProps({
    highlights: { type: Object, required: true },
    companies: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const searchQ = ref(props.filters.q ?? '');
const companyId = ref(props.filters.company_id ? String(props.filters.company_id) : '');
const year = ref(props.filters.year ? String(props.filters.year) : '');
const month = ref(props.filters.month ? String(props.filters.month) : '');
const category = ref(props.filters.category ?? '');

const fieldClass =
    'rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70';

const monthOptions = [
    { value: '1', label: 'Janeiro' },
    { value: '2', label: 'Fevereiro' },
    { value: '3', label: 'Março' },
    { value: '4', label: 'Abril' },
    { value: '5', label: 'Maio' },
    { value: '6', label: 'Junho' },
    { value: '7', label: 'Julho' },
    { value: '8', label: 'Agosto' },
    { value: '9', label: 'Setembro' },
    { value: '10', label: 'Outubro' },
    { value: '11', label: 'Novembro' },
    { value: '12', label: 'Dezembro' },
];

const applyFilters = () => {
    router.get(
        route('admin.destaques-mes.index'),
        {
            company_id: companyId.value || undefined,
            year: year.value || undefined,
            month: month.value || undefined,
            category: category.value || undefined,
            q: searchQ.value || undefined,
        },
        { preserveState: true, replace: true },
    );
};

const createHref = () =>
    route('admin.destaques-mes.create', companyId.value ? { company_id: companyId.value } : {});

const remove = (id) => {
    if (confirm('Remover este destaque do mês?')) {
        router.delete(route('admin.destaques-mes.destroy', id));
    }
};
</script>

<template>
    <Head title="Destaques do mês" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.dashboard')"
                back-label="Dashboard"
                title="Destaques do mês"
                subtitle="Reconheça colaboradores com foto de perfil e categoria do mês"
            >
                <template #trailing>
                    <Link :href="createHref()">
                        <PrimaryButton type="button">Novo destaque</PrimaryButton>
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

        <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-center">
            <div class="relative min-w-0 flex-1">
                <MagnifyingGlassIcon
                    class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                />
                <input
                    v-model="searchQ"
                    type="search"
                    :class="fieldClass + ' w-full pl-9'"
                    placeholder="Buscar por nome, descrição ou empresa…"
                    @keydown.enter.prevent="applyFilters"
                >
            </div>
            <select v-model="companyId" :class="fieldClass" @change="applyFilters">
                <option value="">Todas as empresas</option>
                <option v-for="c in companies" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
            </select>
            <input
                v-model="year"
                type="number"
                min="2000"
                max="2100"
                :class="fieldClass + ' w-28'"
                placeholder="Ano"
                @keydown.enter.prevent="applyFilters"
            >
            <select v-model="month" :class="fieldClass" @change="applyFilters">
                <option value="">Todos os meses</option>
                <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
            <select v-model="category" :class="fieldClass" @change="applyFilters">
                <option value="">Todas as categorias</option>
                <option v-for="cat in categories" :key="cat.value" :value="cat.value">{{ cat.label }}</option>
            </select>
            <PrimaryButton type="button" @click="applyFilters">Filtrar</PrimaryButton>
        </div>

        <div class="surface-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Colaborador</th>
                            <th class="px-4 py-3">Categoria</th>
                            <th class="px-4 py-3">Período</th>
                            <th class="px-4 py-3">Empresa</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 bg-white">
                        <tr v-for="row in highlights.data" :key="row.id" class="hover:bg-slate-50/70">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <img
                                        v-if="row.photo_url"
                                        :src="row.photo_url"
                                        :alt="row.person_name"
                                        class="h-10 w-10 rounded-full object-cover ring-1 ring-slate-200"
                                    >
                                    <div
                                        v-else
                                        class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-500 ring-1 ring-slate-200"
                                    >
                                        {{ (row.person_name || '?').slice(0, 1).toUpperCase() }}
                                    </div>
                                    <span class="font-medium text-slate-900">{{ row.person_name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ row.category_label }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ row.period_label }}</td>
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
                            <td class="space-x-3 px-4 py-3 text-right">
                                <Link
                                    :href="route('admin.destaques-mes.edit', row.id)"
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
                        <tr v-if="!highlights.data?.length">
                            <td colspan="6" class="px-4 py-10 text-center text-slate-500">
                                Nenhum destaque encontrado. Crie o primeiro reconhecimento do mês.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div
            v-if="highlights.links && highlights.links.length > 3"
            class="mt-4 flex flex-wrap justify-end gap-2"
        >
            <template v-for="(link, i) in highlights.links" :key="i">
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
