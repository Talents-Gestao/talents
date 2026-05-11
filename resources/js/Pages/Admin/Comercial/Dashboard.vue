<script setup>
import EmptyState from '@/Components/Dashboard/EmptyState.vue';
import ProgressBar from '@/Components/Dashboard/ProgressBar.vue';
import RankingList from '@/Components/Dashboard/RankingList.vue';
import SectionHeader from '@/Components/Dashboard/SectionHeader.vue';
import StatCard from '@/Components/Dashboard/StatCard.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatBRL } from '@/composables/useCommercialPricing';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    period: { type: String, default: '90d' },
    kpis: { type: Object, required: true },
    deltas: { type: Object, default: () => ({}) },
    byService: { type: Array, default: () => [] },
    bySeller: { type: Array, default: () => [] },
    recent: { type: Array, default: () => [] },
    monthlyClosings: { type: Array, default: () => [] },
    pendingProposals: { type: Array, default: () => [] },
});

const listTab = ref('recent');

const periodOptions = [
    { id: '30d', label: '30 dias' },
    { id: '90d', label: '90 dias' },
    { id: 'year', label: '12 meses' },
    { id: 'all', label: 'Tudo' },
];

const setPeriod = (id) => {
    router.get(route('admin.comercial.dashboard'), { period: id }, { preserveState: true, preserveScroll: true });
};

const formatDate = (iso) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('pt-BR');
};

const conversionDeltaText = computed(() => {
    const d = props.deltas?.conversion_rate;
    if (d === null || d === undefined) return '';
    const sign = d > 0 ? '+' : '';
    return `${sign}${Number(d).toFixed(1)} p.p. vs período anterior`;
});

const topSellersRanking = computed(() =>
    (props.bySeller || []).slice(0, 5).map((row) => ({
        id: row.seller_id,
        name: row.name,
        value: row.closed_total_cents,
        display_value: formatBRL(row.closed_total_cents),
    })),
);

const maxServiceBudget = computed(() => Math.max(1, ...((props.byService || []).map((r) => r.budget_total_cents || 0))));

const maxServiceClosed = computed(() => Math.max(1, ...((props.byService || []).map((r) => r.closed_total_cents || 0))));

const barChartOptions = computed(() => ({
    chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit' },
    plotOptions: { bar: { borderRadius: 6, columnWidth: '55%' } },
    colors: ['#632a7e'],
    dataLabels: { enabled: false },
    xaxis: {
        categories: (props.monthlyClosings || []).map((m) => m.label),
        labels: { style: { colors: '#64748b', fontSize: '11px' } },
    },
    yaxis: {
        labels: {
            formatter: (val) =>
                new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 }).format(
                    Number(val) / 100,
                ),
        },
    },
    grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
    tooltip: {
        theme: 'light',
        y: {
            formatter: (_val, opts) => {
                const row = props.monthlyClosings?.[opts.dataPointIndex];
                if (!row) return '';
                return `${formatBRL(row.total_cents)} · ${row.count} fechamento(s)`;
            },
        },
    },
}));

const barChartSeries = computed(() => [
    {
        name: 'Fechado',
        data: (props.monthlyClosings || []).map((m) => Math.round(m.total_cents / 100)),
    },
]);
</script>

<template>
    <Head title="Comercial — Dashboard" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-slate-500">Painel Comercial</p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">Resumo de propostas</h2>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="inline-flex rounded-xl border border-slate-200 bg-white p-0.5 shadow-sm">
                        <button
                            v-for="opt in periodOptions"
                            :key="opt.id"
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                            :class="period === opt.id ? 'bg-talents-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'"
                            @click="setPeriod(opt.id)"
                        >
                            {{ opt.label }}
                        </button>
                    </div>
                    <Link
                        :href="route('admin.comercial.propostas.index')"
                        class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                    >
                        Ver propostas
                    </Link>
                    <Link
                        :href="route('admin.comercial.propostas.create')"
                        class="inline-flex items-center rounded-xl bg-talents-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-talents-700"
                    >
                        Nova proposta
                    </Link>
                </div>
            </div>
        </template>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                label="Pipeline em aberto"
                :value="formatBRL(kpis.pipeline_open_cents)"
                hint="Soma de propostas não fechadas"
                :detail-href="route('admin.comercial.propostas.index')"
                detail-label="Ver funil"
            />
            <StatCard
                label="Total fechado (período)"
                :value="formatBRL(kpis.total_closed_cents)"
                :hint="`${kpis.closed_count} fechamento(s) por data de fecho`"
                :delta-percent="deltas?.total_closed_cents"
            />
            <StatCard
                label="Ticket médio (criadas no período)"
                :value="formatBRL(kpis.avg_ticket_cents)"
                :hint="`${kpis.total_count} proposta(s) criada(s)`"
                :delta-percent="deltas?.avg_ticket_cents"
            />
            <StatCard
                label="Conversão (coorte)"
                :value="`${Number(kpis.conversion_rate).toFixed(1)}%`"
                hint="Criadas no período que já estão fechadas"
                :delta-percent="deltas?.conversion_rate"
                :delta-text-override="conversionDeltaText"
            />
        </div>

        <div class="mt-2 text-xs text-slate-500">
            Comissão no período:
            <span class="font-semibold text-violet-700">{{ formatBRL(kpis.commission_total_cents) }}</span>
            <span v-if="deltas?.commission_total_cents !== null && deltas?.commission_total_cents !== undefined" class="ml-2">
                <span
                    :class="
                        deltas.commission_total_cents > 0
                            ? 'text-emerald-600'
                            : deltas.commission_total_cents < 0
                              ? 'text-rose-600'
                              : 'text-slate-500'
                    "
                >
                    {{ deltas.commission_total_cents > 0 ? '+' : '' }}{{ deltas.commission_total_cents?.toFixed(1) }}% vs anterior
                </span>
            </span>
        </div>

        <div class="mt-8 grid gap-8 lg:grid-cols-2">
            <div class="surface-card border-slate-200/70 p-5 sm:p-6">
                <SectionHeader title="Fechamentos por mês" subtitle="Últimos 6 meses (data de fecho)" />
                <div v-if="monthlyClosings?.length" class="mt-2">
                    <apexchart type="bar" height="280" :options="barChartOptions" :series="barChartSeries" />
                </div>
                <EmptyState v-else class="mt-4 border-0 bg-transparent" title="Sem fechamentos" />
            </div>

            <div class="surface-card border-slate-200/70 p-5 sm:p-6">
                <SectionHeader title="Top vendedores" subtitle="Por valor fechado no período (closed_at)" />
                <RankingList v-if="topSellersRanking.length" :items="topSellersRanking" class="mt-2" />
                <EmptyState v-else class="mt-4 border-0 bg-transparent" title="Sem vendedores" />
            </div>
        </div>

        <div class="mt-8 surface-card border-slate-200/70 p-5 sm:p-6">
            <SectionHeader title="Resumo por serviço" subtitle="Orçado = criadas no período · Fechado = data de fecho no período · Conversão = coorte criada no período" />
            <div class="mt-4 space-y-4">
                <div v-for="row in byService" :key="row.label" class="rounded-2xl border border-slate-100 bg-slate-50/50 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <p class="font-semibold text-slate-900">{{ row.label }}</p>
                        <span class="text-xs font-medium text-slate-500">Conversão {{ Number(row.conversion_rate).toFixed(1) }}%</span>
                    </div>
                    <ProgressBar
                        class="mt-3"
                        label="Orçado (valor do serviço)"
                        :value="(100 * row.budget_total_cents) / maxServiceBudget"
                        :display-value="`${formatBRL(row.budget_total_cents)} · ${row.budget_count} prop.`"
                        bar-class="bg-slate-400"
                    />
                    <ProgressBar
                        class="mt-2"
                        label="Fechado (valor do serviço)"
                        :value="(100 * row.closed_total_cents) / maxServiceClosed"
                        :display-value="`${formatBRL(row.closed_total_cents)} · ${row.closed_count} prop.`"
                        bar-class="bg-emerald-600"
                    />
                </div>
                <EmptyState v-if="!byService.length" title="Sem serviços" />
            </div>
        </div>

        <div class="mt-8 surface-card border-slate-200/70 p-5 sm:p-6">
            <div class="flex flex-wrap gap-2 border-b border-slate-100 pb-3">
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-sm font-semibold transition"
                    :class="listTab === 'recent' ? 'bg-talents-100 text-talents-900' : 'text-slate-600 hover:bg-slate-50'"
                    @click="listTab = 'recent'"
                >
                    Últimas propostas
                </button>
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-sm font-semibold transition"
                    :class="listTab === 'stalled' ? 'bg-amber-100 text-amber-900' : 'text-slate-600 hover:bg-slate-50'"
                    @click="listTab = 'stalled'"
                >
                    Estagnadas (+30 dias)
                </button>
            </div>

            <div v-if="listTab === 'recent'" class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="py-2 pr-3 text-left font-medium">Código</th>
                            <th class="px-3 py-2 text-left font-medium">Cliente</th>
                            <th class="px-3 py-2 text-left font-medium">Vendedor</th>
                            <th class="px-3 py-2 text-right font-medium">Func.</th>
                            <th class="px-3 py-2 text-right font-medium">Total</th>
                            <th class="px-3 py-2 text-left font-medium">Status</th>
                            <th class="px-3 py-2 text-right font-medium">Criada</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="p in recent" :key="p.id" class="hover:bg-slate-50">
                            <td class="py-2 pr-3 font-mono text-xs text-slate-600">
                                <Link :href="route('admin.comercial.propostas.edit', p.id)" class="hover:underline">
                                    {{ p.code }}
                                </Link>
                            </td>
                            <td class="px-3 py-2 font-medium">{{ p.client_name }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ p.seller?.name ?? '—' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ p.employee_count }}</td>
                            <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ formatBRL(p.total_final_cents) }}</td>
                            <td class="px-3 py-2">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="p.is_closed ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                >
                                    {{ p.is_closed ? 'Fechada' : 'Em aberto' }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right text-xs text-slate-500">{{ formatDate(p.created_at) }}</td>
                        </tr>
                        <tr v-if="!recent.length">
                            <td colspan="7" class="py-8 text-center text-slate-500">Nenhuma proposta no período.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="py-2 pr-3 text-left font-medium">Código</th>
                            <th class="px-3 py-2 text-left font-medium">Cliente</th>
                            <th class="px-3 py-2 text-left font-medium">Vendedor</th>
                            <th class="px-3 py-2 text-right font-medium">Total</th>
                            <th class="px-3 py-2 text-right font-medium">Criada</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="p in pendingProposals" :key="p.id" class="hover:bg-slate-50">
                            <td class="py-2 pr-3 font-mono text-xs text-slate-600">
                                <Link :href="route('admin.comercial.propostas.edit', p.id)" class="hover:underline">
                                    {{ p.code }}
                                </Link>
                            </td>
                            <td class="px-3 py-2 font-medium">{{ p.client_name }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ p.seller?.name ?? '—' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ formatBRL(p.total_final_cents) }}</td>
                            <td class="px-3 py-2 text-right text-xs text-slate-500">{{ formatDate(p.created_at) }}</td>
                        </tr>
                        <tr v-if="!pendingProposals.length">
                            <td colspan="5" class="py-8 text-center text-slate-500">Nenhuma proposta aberta há mais de 30 dias.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-8 surface-card border-slate-200/70 p-5 sm:p-6">
            <SectionHeader title="Todos os vendedores" subtitle="Orçado vs fechado no período" />
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="py-2 pr-3 text-left font-medium">Vendedor</th>
                            <th class="px-3 py-2 text-right font-medium">Conv.</th>
                            <th class="px-3 py-2 text-right font-medium">Orçado</th>
                            <th class="px-3 py-2 text-right font-medium">Fechado</th>
                            <th class="px-3 py-2 text-right font-medium">Comissão</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in bySeller" :key="row.seller_id">
                            <td class="py-2 pr-3 font-medium text-slate-800">{{ row.name }}</td>
                            <td class="px-3 py-2 text-right tabular-nums text-xs text-slate-600">
                                {{ Number(row.conversion_rate).toFixed(1) }}%
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                <div>{{ formatBRL(row.budget_total_cents) }}</div>
                                <div class="text-xs text-slate-500">{{ row.budget_count }} prop.</div>
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                <div class="text-emerald-700">{{ formatBRL(row.closed_total_cents) }}</div>
                                <div class="text-xs text-slate-500">{{ row.closed_count }} fecho(s)</div>
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums text-violet-700">
                                {{ formatBRL(row.commission_total_cents) }}
                            </td>
                        </tr>
                        <tr v-if="!bySeller.length">
                            <td colspan="5" class="py-8 text-center text-slate-500">Nenhum vendedor comercial.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
