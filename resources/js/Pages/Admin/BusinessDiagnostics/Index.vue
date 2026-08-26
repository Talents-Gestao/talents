<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import { ref } from 'vue';

const props = defineProps({
    diagnostics: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const searchQ = ref(props.filters.q ?? '');

const applyFilters = () => {
    router.get(
        route('admin.diagnostico-empresarial.index'),
        { q: searchQ.value || undefined },
        { preserveState: true, replace: true },
    );
};

const remove = (id) => {
    if (confirm('Remover este diagnóstico?')) {
        router.delete(route('admin.diagnostico-empresarial.destroy', id));
    }
};
</script>

<template>
    <Head title="Diagnóstico empresarial" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.companies.index')"
                back-label="Clientes"
                title="Diagnóstico empresarial"
                subtitle="Diagnósticos de maturidade em gestão de pessoas registrados pela equipe Talents"
            >
                <template #trailing>
                    <Link :href="route('admin.diagnostico-empresarial.create')">
                        <PrimaryButton type="button">Novo diagnóstico</PrimaryButton>
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
                    class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70"
                    placeholder="Buscar por empresa, CNPJ, responsável ou e-mail…"
                    @keyup.enter="applyFilters"
                />
            </div>
            <button
                type="button"
                class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                @click="applyFilters"
            >
                Filtrar
            </button>
        </div>

        <div class="surface-card overflow-hidden">
            <div v-if="!diagnostics.data.length" class="px-4 py-10 text-center text-sm text-slate-600">
                Nenhum diagnóstico registrado.
            </div>
            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm text-slate-900">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-slate-700">Data</th>
                            <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-slate-700">Empresa</th>
                            <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-slate-700">Responsável</th>
                            <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-slate-700">Contato</th>
                            <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-slate-700">Maturidade</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right font-medium text-slate-700">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <tr v-for="row in diagnostics.data" :key="row.id">
                            <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                                {{
                                    row.created_at
                                        ? new Date(row.created_at).toLocaleString('pt-BR', {
                                              dateStyle: 'short',
                                              timeStyle: 'short',
                                          })
                                        : '—'
                                }}
                            </td>
                            <td class="px-4 py-3">
                                <Link
                                    :href="route('admin.diagnostico-empresarial.show', row.id)"
                                    class="font-medium text-talents-700 hover:underline"
                                >
                                    {{ row.company_name }}
                                </Link>
                                <p v-if="row.cnpj" class="text-xs text-slate-500">{{ row.cnpj }}</p>
                            </td>
                            <td class="px-4 py-3">{{ row.responsible_name }}</td>
                            <td class="px-4 py-3">
                                <a :href="'mailto:' + row.email" class="text-talents-700 hover:underline">{{
                                    row.email
                                }}</a>
                                <p v-if="row.phone" class="text-xs text-slate-500">{{ row.phone }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    v-if="row.hr_maturity != null"
                                    class="inline-flex rounded-full bg-talents-50 px-2.5 py-0.5 text-xs font-semibold text-talents-800 ring-1 ring-talents-200/80"
                                >
                                    {{ row.hr_maturity }}/10
                                </span>
                                <span v-else class="text-slate-400">—</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <Link
                                    :href="route('admin.diagnostico-empresarial.edit', row.id)"
                                    class="mr-3 text-sm font-medium text-talents-700 hover:underline"
                                >
                                    Editar
                                </Link>
                                <button
                                    type="button"
                                    class="text-sm font-medium text-red-600 hover:underline"
                                    @click="remove(row.id)"
                                >
                                    Remover
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="diagnostics.prev_page_url || diagnostics.next_page_url"
                class="flex items-center justify-between border-t border-slate-200 px-4 py-3 text-sm"
            >
                <Link
                    v-if="diagnostics.prev_page_url"
                    :href="diagnostics.prev_page_url"
                    class="font-medium text-talents-700 hover:underline"
                >
                    Anterior
                </Link>
                <span v-else />
                <span class="text-slate-500">
                    Página {{ diagnostics.current_page }} de {{ diagnostics.last_page }}
                </span>
                <Link
                    v-if="diagnostics.next_page_url"
                    :href="diagnostics.next_page_url"
                    class="font-medium text-talents-700 hover:underline"
                >
                    Seguinte
                </Link>
                <span v-else />
            </div>
        </div>
    </AdminLayout>
</template>
