<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { MagnifyingGlassIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { ref } from 'vue';

const props = defineProps({
    employees: { type: Object, required: true },
    companies: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const searchQ = ref(props.filters.q ?? '');
const companyId = ref(props.filters.company_id ? String(props.filters.company_id) : '');

const fieldClass =
    'rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70';

const applyFilters = () => {
    router.get(
        route('admin.colaboradores.index'),
        {
            company_id: companyId.value || undefined,
            q: searchQ.value || undefined,
        },
        { preserveState: true, replace: true },
    );
};

const createHref = () => {
    if (!companyId.value) {
        return null;
    }
    return route('admin.colaboradores.create', { company_id: companyId.value });
};

const remove = (id) => {
    if (confirm('Remover este colaborador?')) {
        router.delete(route('admin.colaboradores.destroy', id));
    }
};
</script>

<template>
    <Head title="Cadastro de colaboradores" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.dashboard')"
                back-label="Dashboard"
                title="Cadastro de colaboradores"
                subtitle="Ficha completa dos colaboradores por empresa"
            >
                <template #trailing>
                    <Link v-if="createHref()" :href="createHref()">
                        <PrimaryButton type="button">Novo colaborador</PrimaryButton>
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
                    placeholder="Buscar por nome, e-mail, CPF ou telefone"
                    @keyup.enter="applyFilters"
                />
            </div>
            <select v-model="companyId" :class="fieldClass + ' sm:w-64'" @change="applyFilters">
                <option value="">Todas as empresas</option>
                <option v-for="c in companies" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
            </select>
            <button
                type="button"
                class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                @click="applyFilters"
            >
                Filtrar
            </button>
        </div>

        <p v-if="!companyId" class="mb-4 rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Selecione uma empresa para cadastrar um novo colaborador.
        </p>

        <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
            <div v-if="employees.data?.length" class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead
                        class="border-b border-slate-100 bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                    >
                        <tr>
                            <th class="px-5 py-3">Nome</th>
                            <th class="px-5 py-3">Empresa</th>
                            <th class="px-5 py-3">Cargo</th>
                            <th class="px-5 py-3">Setor</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="e in employees.data" :key="e.id" class="transition hover:bg-talents-50/30">
                            <td class="px-5 py-3.5">
                                <Link
                                    :href="route('admin.colaboradores.show', e.id)"
                                    class="font-medium text-talents-800 hover:text-talents-600"
                                >
                                    {{ e.name }}
                                </Link>
                                <p v-if="e.email" class="mt-0.5 text-xs text-slate-500">{{ e.email }}</p>
                            </td>
                            <td class="px-5 py-3.5 text-slate-600">{{ e.company?.name ?? '—' }}</td>
                            <td class="px-5 py-3.5 text-slate-600">{{ e.position?.name ?? '—' }}</td>
                            <td class="px-5 py-3.5 text-slate-600">{{ e.department?.name ?? '—' }}</td>
                            <td class="px-5 py-3.5">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset"
                                    :class="
                                        e.is_active
                                            ? 'bg-emerald-50 text-emerald-800 ring-emerald-200'
                                            : 'bg-slate-100 text-slate-600 ring-slate-200'
                                    "
                                >
                                    {{ e.is_active ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-end gap-1">
                                    <Link
                                        :href="route('admin.colaboradores.edit', e.id)"
                                        class="rounded-lg p-2 text-slate-500 transition hover:bg-talents-50 hover:text-talents-700"
                                        title="Editar"
                                    >
                                        <PencilSquareIcon class="h-4 w-4" />
                                    </Link>
                                    <button
                                        type="button"
                                        class="rounded-lg p-2 text-slate-500 transition hover:bg-rose-50 hover:text-rose-600"
                                        title="Excluir"
                                        @click="remove(e.id)"
                                    >
                                        <TrashIcon class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="px-5 py-12 text-center text-sm text-slate-500">
                Nenhum colaborador encontrado. Selecione uma empresa e cadastre a primeira ficha.
            </div>
        </div>
    </AdminLayout>
</template>
