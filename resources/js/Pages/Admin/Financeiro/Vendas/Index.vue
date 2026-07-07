<script setup>
import FinanceModuleNav from '@/Components/Financeiro/FinanceModuleNav.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatBRL } from '@/composables/useCommercialPricing';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    sales: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    sellers: { type: Array, default: () => [] },
    statusOptions: { type: Object, default: () => ({}) },
});

const filterState = reactive({
    search: props.filters.search ?? '',
    seller_id: props.filters.seller_id ?? '',
    status: props.filters.status ?? '',
});

const applyFilters = () => {
    router.get(route('admin.financeiro.vendas.index'), filterState, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const clearFilters = () => {
    filterState.search = '';
    filterState.seller_id = '';
    filterState.status = '';
    applyFilters();
};

const formatDate = (iso) => (iso ? new Date(iso).toLocaleDateString('pt-BR') : '—');

const statusLabel = (s) => props.statusOptions[s] ?? s;

const statusClass = (s) =>
    ({
        aberta: 'bg-amber-100 text-amber-800',
        parcial: 'bg-sky-100 text-sky-800',
        quitada: 'bg-emerald-100 text-emerald-800',
        cancelada: 'bg-slate-100 text-slate-600',
    }[s] ?? 'bg-slate-100 text-slate-600');
</script>

<template>
    <Head title="Financeiro — Vendas" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-slate-500">Financeiro</p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">Vendas</h2>
                </div>
                <Link
                    :href="route('admin.financeiro.dashboard')"
                    class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                >
                    Painel financeiro
                </Link>
            </div>
        </template>

        <FinanceModuleNav />

        <div class="surface-card p-6">
            <form class="grid gap-4 sm:grid-cols-4" @submit.prevent="applyFilters">
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
                    <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Status</label>
                    <select
                        v-model="filterState.status"
                        class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                        <option value="">Todos</option>
                        <option v-for="(label, key) in statusOptions" :key="key" :value="key">{{ label }}</option>
                    </select>
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
                            <th class="px-4 py-3 text-left font-medium">Proposta</th>
                            <th class="px-4 py-3 text-left font-medium">Cliente</th>
                            <th class="px-4 py-3 text-left font-medium">Vendedor</th>
                            <th class="px-4 py-3 text-right font-medium">Total</th>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">Pendentes</th>
                            <th class="px-4 py-3 text-right font-medium">Vendida em</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="sale in sales.data" :key="sale.id" class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <Link
                                    :href="route('admin.financeiro.vendas.show', sale.id)"
                                    class="font-mono text-xs text-talents-700 hover:underline"
                                >
                                    {{ sale.code }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ sale.proposal?.code ?? '—' }}</td>
                            <td class="px-4 py-3 font-medium">{{ sale.client_name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ sale.seller?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold">{{ formatBRL(sale.total_cents) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(sale.status)">
                                    {{ statusLabel(sale.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ sale.pending_installments_count ?? 0 }}</td>
                            <td class="px-4 py-3 text-right text-xs text-slate-500">{{ formatDate(sale.sold_at) }}</td>
                        </tr>
                        <tr v-if="!sales.data?.length">
                            <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">Nenhuma venda encontrada.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
