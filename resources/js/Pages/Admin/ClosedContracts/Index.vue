<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatBRL } from '@/composables/useCommercialPricing';
import { formatCnpj } from '@/utils/formatCnpj';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    proposals: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    sellers: { type: Array, default: () => [] },
});

const filterState = reactive({
    search: props.filters.search ?? '',
    seller_id: props.filters.seller_id ?? '',
    closed_from: props.filters.closed_from ?? '',
    closed_to: props.filters.closed_to ?? '',
    has_contract: Boolean(props.filters.has_contract),
    has_sale: Boolean(props.filters.has_sale),
});

const applyFilters = () => {
    router.get(route('admin.contratos-fechados.index'), filterState, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const clearFilters = () => {
    filterState.search = '';
    filterState.seller_id = '';
    filterState.closed_from = '';
    filterState.closed_to = '';
    filterState.has_contract = false;
    filterState.has_sale = false;
    applyFilters();
};

const formatDate = (iso) => (iso ? new Date(iso).toLocaleDateString('pt-BR') : '—');
</script>

<template>
    <Head title="Contratos fechados" />

    <AdminLayout>
        <template #header>
            <div>
                <p class="text-sm text-slate-500">Clientes</p>
                <h2 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">Contratos fechados</h2>
                <p class="mt-1 max-w-2xl text-sm text-slate-600">
                    Histórico de propostas fechadas — portfólio de fechamentos por cliente (texto/CNPJ).
                </p>
            </div>
        </template>

        <div class="surface-card p-6">
            <form class="grid gap-4 grid-cols-1 md:grid-cols-2 xl:grid-cols-4" @submit.prevent="applyFilters">
                <div class="sm:col-span-2">
                    <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Buscar</label>
                    <input
                        v-model="filterState.search"
                        type="text"
                        class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        placeholder="Cliente, código ou CNPJ"
                    />
                </div>
                <div>
                    <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Vendedor</label>
                    <select
                        v-model="filterState.seller_id"
                        class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                        <option value="">Todos</option>
                        <option v-for="s in sellers" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Fechamento de</label>
                    <input
                        v-model="filterState.closed_from"
                        type="date"
                        class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    />
                </div>
                <div>
                    <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Fechamento até</label>
                    <input
                        v-model="filterState.closed_to"
                        type="date"
                        class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    />
                </div>
                <div class="flex items-end gap-4 sm:col-span-2">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input
                            v-model="filterState.has_contract"
                            type="checkbox"
                            class="rounded border-slate-300 text-talents-700 focus:ring-talents-500"
                        />
                        Com contrato PDF
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input
                            v-model="filterState.has_sale"
                            type="checkbox"
                            class="rounded border-slate-300 text-talents-700 focus:ring-talents-500"
                        />
                        Com venda
                    </label>
                </div>
                <div class="sm:col-span-4 flex justify-end gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                        @click="clearFilters"
                    >
                        Limpar
                    </button>
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800"
                    >
                        Filtrar
                    </button>
                </div>
            </form>
        </div>

        <div class="mt-6 surface-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Código</th>
                            <th class="px-4 py-3 text-left font-medium">Cliente</th>
                            <th class="px-4 py-3 text-left font-medium">Fechamento</th>
                            <th class="px-4 py-3 text-right font-medium">Valor</th>
                            <th class="px-4 py-3 text-left font-medium">Vendedor</th>
                            <th class="px-4 py-3 text-left font-medium">Estado</th>
                            <th class="px-4 py-3 text-right font-medium">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="row in proposals.data" :key="row.id" class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-mono text-xs text-talents-700">{{ row.code }}</td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ row.client_name }}</p>
                                <p v-if="row.client_cnpj" class="mt-0.5 text-xs text-slate-500">
                                    {{ formatCnpj(row.client_cnpj) }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ formatDate(row.closed_at) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold">
                                {{ formatBRL(row.total_final_cents) }}
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ row.seller?.name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800"
                                    >
                                        Aprovada
                                    </span>
                                    <span
                                        v-if="row.badges.has_contract"
                                        class="inline-flex rounded-full bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-800"
                                    >
                                        Contrato PDF
                                    </span>
                                    <span
                                        v-if="row.badges.has_zapsign"
                                        class="inline-flex rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-800"
                                    >
                                        ZapSign
                                    </span>
                                    <span
                                        v-if="row.badges.has_sale"
                                        class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800"
                                    >
                                        Venda
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <Link
                                        :href="route('admin.comercial.propostas.edit', row.id)"
                                        class="text-sm font-medium text-talents-700 hover:underline"
                                    >
                                        Proposta
                                    </Link>
                                    <a
                                        v-if="row.latest_contract_id"
                                        :href="route('admin.comercial.contratos.pdf', row.latest_contract_id)"
                                        class="text-sm font-medium text-slate-700 hover:underline"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        PDF
                                    </a>
                                    <Link
                                        v-if="row.sale_id"
                                        :href="route('admin.financeiro.vendas.show', row.sale_id)"
                                        class="text-sm font-medium text-slate-700 hover:underline"
                                    >
                                        Venda
                                    </Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!proposals.data?.length">
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">
                                Nenhuma proposta fechada encontrada.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                v-if="proposals.prev_page_url || proposals.next_page_url"
                class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm"
            >
                <Link
                    v-if="proposals.prev_page_url"
                    :href="proposals.prev_page_url"
                    class="font-medium text-talents-700 hover:underline"
                    preserve-scroll
                >
                    Anterior
                </Link>
                <span v-else class="text-slate-400">Anterior</span>
                <span class="text-slate-500">
                    Página {{ proposals.current_page }} de {{ proposals.last_page }}
                </span>
                <Link
                    v-if="proposals.next_page_url"
                    :href="proposals.next_page_url"
                    class="font-medium text-talents-700 hover:underline"
                    preserve-scroll
                >
                    Próxima
                </Link>
                <span v-else class="text-slate-400">Próxima</span>
            </div>
        </div>
    </AdminLayout>
</template>
