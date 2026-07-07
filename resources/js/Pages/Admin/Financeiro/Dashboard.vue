<script setup>
import FinanceModuleNav from '@/Components/Financeiro/FinanceModuleNav.vue';
import StatCard from '@/Components/Dashboard/StatCard.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatBRL } from '@/composables/useCommercialPricing';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    period: { type: String, default: '90d' },
    kpis: { type: Object, required: true },
    upcomingInstallments: { type: Array, default: () => [] },
    overdueInstallments: { type: Array, default: () => [] },
    recentSales: { type: Array, default: () => [] },
});

const periodOptions = [
    { id: '30d', label: '30 dias' },
    { id: '90d', label: '90 dias' },
    { id: 'year', label: '12 meses' },
    { id: 'all', label: 'Tudo' },
];

const setPeriod = (id) => {
    router.get(route('admin.financeiro.dashboard'), { period: id }, { preserveState: true, preserveScroll: true });
};

const formatDate = (iso) => (iso ? new Date(iso).toLocaleDateString('pt-BR') : '—');

const methodLabel = (m) => ({ pix: 'PIX', boleto: 'Boleto', cartao: 'Cartão' }[m] ?? m);

const statusLabel = (s) =>
    ({
        aberta: 'Aberta',
        parcial: 'Parcial',
        quitada: 'Quitada',
        cancelada: 'Cancelada',
    }[s] ?? s);

const statusClass = (s) =>
    ({
        aberta: 'bg-amber-100 text-amber-800',
        parcial: 'bg-sky-100 text-sky-800',
        quitada: 'bg-emerald-100 text-emerald-800',
        cancelada: 'bg-slate-100 text-slate-600',
    }[s] ?? 'bg-slate-100 text-slate-600');
</script>

<template>
    <Head title="Financeiro" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-slate-500">Comercial / Clientes</p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">Financeiro</h2>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="route('admin.financeiro.comissoes.index')"
                        class="inline-flex items-center rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-900 shadow-sm transition hover:bg-amber-100"
                    >
                        Comissões
                    </Link>
                    <Link
                        :href="route('admin.financeiro.vendas.index')"
                        class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                    >
                        Ver vendas
                    </Link>
                </div>
            </div>
        </template>

        <FinanceModuleNav />

        <div class="mb-6 flex flex-wrap gap-2">
            <button
                v-for="opt in periodOptions"
                :key="opt.id"
                type="button"
                class="rounded-full px-3 py-1 text-sm font-medium transition"
                :class="
                    period === opt.id
                        ? 'bg-talents-600 text-white'
                        : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'
                "
                @click="setPeriod(opt.id)"
            >
                {{ opt.label }}
            </button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="A receber" :value="formatBRL(kpis.receivable_cents)" />
            <StatCard label="Recebido no período" :value="formatBRL(kpis.received_cents)" />
            <StatCard label="Vencidos" :value="formatBRL(kpis.overdue_cents)" />
            <StatCard label="Comissões a pagar" :value="formatBRL(kpis.commissions_pending_cents)" />
        </div>

        <div class="mb-8">
            <Link
                :href="route('admin.financeiro.comissoes.index', { status: 'a_pagar' })"
                class="text-sm font-semibold text-talents-700 hover:underline"
            >
                Ver todas as comissões pendentes →
            </Link>
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-2">
            <div class="surface-card p-6">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Próximos vencimentos</h3>
                <ul v-if="upcomingInstallments.length" class="mt-4 divide-y divide-slate-100">
                    <li v-for="item in upcomingInstallments" :key="item.id" class="flex items-center justify-between py-3 text-sm">
                        <div>
                            <Link
                                :href="route('admin.financeiro.vendas.show', item.sale_id)"
                                class="font-medium text-talents-700 hover:underline"
                            >
                                {{ item.sale?.code }} — {{ item.sale?.client_name }}
                            </Link>
                            <p class="text-xs text-slate-500">
                                Parcela {{ item.number }} · {{ methodLabel(item.method) }} · {{ formatDate(item.due_date) }}
                            </p>
                        </div>
                        <span class="font-semibold tabular-nums">{{ formatBRL(item.amount_cents) }}</span>
                    </li>
                </ul>
                <p v-else class="mt-4 text-sm text-slate-500">Nenhuma parcela pendente.</p>
            </div>

            <div class="surface-card p-6">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-red-600">Parcelas vencidas</h3>
                <ul v-if="overdueInstallments.length" class="mt-4 divide-y divide-slate-100">
                    <li v-for="item in overdueInstallments" :key="item.id" class="flex items-center justify-between py-3 text-sm">
                        <div>
                            <Link
                                :href="route('admin.financeiro.vendas.show', item.sale_id)"
                                class="font-medium text-talents-700 hover:underline"
                            >
                                {{ item.sale?.code }} — {{ item.sale?.client_name }}
                            </Link>
                            <p class="text-xs text-red-600">
                                Parcela {{ item.number }} · venceu em {{ formatDate(item.due_date) }}
                            </p>
                        </div>
                        <span class="font-semibold tabular-nums">{{ formatBRL(item.amount_cents) }}</span>
                    </li>
                </ul>
                <p v-else class="mt-4 text-sm text-slate-500">Nenhuma parcela vencida.</p>
            </div>
        </div>

        <div class="mt-8 surface-card overflow-hidden">
            <div class="border-b border-slate-100 px-6 py-4">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Vendas recentes</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Código</th>
                            <th class="px-4 py-3 text-left font-medium">Cliente</th>
                            <th class="px-4 py-3 text-left font-medium">Vendedor</th>
                            <th class="px-4 py-3 text-right font-medium">Total</th>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">Vendida em</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="sale in recentSales" :key="sale.id" class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <Link
                                    :href="route('admin.financeiro.vendas.show', sale.id)"
                                    class="font-mono text-xs text-talents-700 hover:underline"
                                >
                                    {{ sale.code }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 font-medium">{{ sale.client_name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ sale.seller?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold">
                                {{ formatBRL(sale.total_cents) }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(sale.status)">
                                    {{ statusLabel(sale.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right text-xs text-slate-500">{{ formatDate(sale.sold_at) }}</td>
                        </tr>
                        <tr v-if="!recentSales.length">
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">Nenhuma venda no período.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
