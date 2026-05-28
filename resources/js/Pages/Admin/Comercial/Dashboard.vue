<script setup>
import EmptyState from '@/Components/Dashboard/EmptyState.vue';
import ProgressBar from '@/Components/Dashboard/ProgressBar.vue';
import RankingList from '@/Components/Dashboard/RankingList.vue';
import SectionHeader from '@/Components/Dashboard/SectionHeader.vue';
import StatCard from '@/Components/Dashboard/StatCard.vue';
import CommercialModuleNav from '@/Components/Comercial/CommercialModuleNav.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useDashboardGreeting } from '@/composables/useDashboardGreeting';
import { formatBRL } from '@/composables/useCommercialPricing';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const greeting = useDashboardGreeting();

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

const stalledCount = computed(() => props.pendingProposals?.length ?? 0);

const monthlyClosingsTotalCents = computed(() => (props.monthlyClosings || []).reduce((acc, m) => acc + (m.total_cents || 0), 0));

const userInitials = computed(() => {
    const full = greeting.value.full || '';
    const parts = full.split(/\s+/).filter(Boolean);
    if (parts.length >= 2) {
        return `${parts[0][0] ?? ''}${parts[1][0] ?? ''}`.toUpperCase();
    }
    return (parts[0]?.slice(0, 2) || 'CO').toUpperCase();
});

const barChartOptions = computed(() => ({
    chart: {
        type: 'bar',
        toolbar: { show: false },
        fontFamily: 'inherit',
        dropShadow: { enabled: true, top: 6, left: 0, blur: 6, opacity: 0.08 },
    },
    states: {
        hover: { filter: { type: 'darken', value: 0.88 } },
        active: { filter: { type: 'none', value: 0 } },
    },
    plotOptions: { bar: { borderRadius: 8, columnWidth: '58%' } },
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
                <div class="min-w-0">
                    <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Comercial</p>
                    <h2 class="mt-0.5 text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">Resumo de propostas</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ greeting.prefix }}, <span class="font-semibold text-slate-900">{{ greeting.first }}</span>
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-if="stalledCount > 0"
                        type="button"
                        class="dashboard-soft-badge"
                        @click="listTab = 'stalled'"
                    >
                        <span class="dashboard-soft-badge-count">
                            {{ stalledCount }}
                        </span>
                        Estagnadas
                    </button>
                    <label class="sr-only" for="comercial-periodo">Período</label>
                    <select
                        id="comercial-periodo"
                        class="cursor-pointer rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-800 shadow-sm transition hover:border-talents-300 focus:border-talents-500 focus:outline-none focus:ring-2 focus:ring-talents-200"
                        :value="period"
                        @change="setPeriod($event.target.value)"
                    >
                        <option v-for="opt in periodOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
                    </select>
                    <Link
                        v-if="$page.props.auth?.user?.can_commercial_settings"
                        :href="route('admin.comercial.settings.edit')"
                        class="inline-flex items-center rounded-xl border border-talents-200 bg-talents-50 px-4 py-2 text-sm font-semibold text-talents-800 shadow-sm transition hover:bg-talents-100"
                    >
                        Valores e contratos
                    </Link>
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

        <CommercialModuleNav />

        <!-- Destaque + ação rápida -->
        <div class="mb-8 grid gap-4 lg:grid-cols-3">
            <div class="dashboard-hero lg:col-span-2">
                <div class="dashboard-hero-blob-accent right-0 top-0 h-40 w-40 translate-x-1/4 -translate-y-1/4" />
                <div class="relative flex flex-wrap items-start justify-between gap-6">
                    <div class="flex min-w-0 gap-4">
                        <div
                            class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/15 text-lg font-bold ring-2 ring-white/25"
                            aria-hidden="true"
                        >
                            {{ userInitials }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wider text-white/70">Pipeline atual</p>
                            <p class="mt-2 font-serif text-3xl font-bold tracking-tight sm:text-4xl">
                                {{ formatBRL(kpis.pipeline_open_cents) }}
                            </p>
                            <p class="mt-2 max-w-md text-sm text-white/85">Soma de todas as propostas em aberto — passe o rato nas barras do relatório mensal para detalhes.</p>
                        </div>
                    </div>
                    <Link
                        :href="route('admin.comercial.propostas.index')"
                        class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white text-talents-700 shadow-lg transition hover:scale-105 hover:bg-slate-50"
                        aria-label="Abrir propostas"
                    >
                        <svg class="h-5 w-5 translate-x-px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </Link>
                </div>
            </div>
            <button
                type="button"
                class="dashboard-accent-dark text-white focus:outline-none focus:ring-2 focus:ring-talents-400/50"
                @click="listTab = 'stalled'"
            >
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Atenção</p>
                    <h3 class="mt-2 text-lg font-bold">Propostas paradas</h3>
                    <p class="mt-2 text-sm text-slate-400">Abertas há mais de 30 dias sem fecho.</p>
                </div>
                <p class="mt-6 font-serif text-4xl font-bold text-highlight">{{ stalledCount }}</p>
                <span class="mt-2 text-sm font-semibold text-white/90">Toque para ver a lista →</span>
            </button>
        </div>

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
            <span class="font-semibold text-talents-700">{{ formatBRL(kpis.commission_total_cents) }}</span>
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

        <div class="dashboard-panel mt-8">
            <SectionHeader
                variant="panel"
                title="Relatório de fechamentos"
                subtitle="Últimos 6 meses (valor fechado por mês)"
            />
            <div class="mt-6 flex flex-col gap-8 lg:flex-row lg:items-stretch">
                <div class="min-h-[280px] min-w-0 flex-1">
                    <apexchart v-if="monthlyClosings?.length" type="bar" height="300" :options="barChartOptions" :series="barChartSeries" />
                    <EmptyState v-else class="border-0 bg-transparent py-12" title="Sem fechamentos" />
                </div>
                <aside
                    v-if="monthlyClosings?.length"
                    class="dashboard-chart-aside lg:w-64"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Por mês</p>
                    <ul class="mt-3 max-h-64 space-y-2.5 overflow-y-auto text-sm">
                        <li v-for="m in monthlyClosings" :key="m.month" class="flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2 text-slate-700">
                                <span class="h-2 w-2 shrink-0 rounded-full bg-talents-600" />
                                {{ m.label }}
                            </span>
                            <span class="shrink-0 text-xs font-semibold tabular-nums text-slate-900">{{ formatBRL(m.total_cents) }}</span>
                        </li>
                    </ul>
                    <div class="mt-4 border-t border-slate-200 pt-4">
                        <p class="text-xs text-slate-500">Total (6 meses)</p>
                        <p class="font-serif text-2xl font-bold text-talents-800">{{ formatBRL(monthlyClosingsTotalCents) }}</p>
                    </div>
                </aside>
            </div>
            <div class="mt-8 border-t border-slate-100 pt-6">
                <SectionHeader title="Top vendedores" subtitle="Por valor fechado no período (closed_at)" />
                <RankingList v-if="topSellersRanking.length" :items="topSellersRanking" class="mt-2" />
                <EmptyState v-else class="mt-4 border-0 bg-transparent" title="Sem vendedores" />
            </div>
        </div>

        <div class="dashboard-panel mt-8">
            <SectionHeader variant="panel" title="Resumo por serviço" subtitle="Orçado = criadas no período · Fechado = data de fecho no período · Conversão = coorte criada no período" />
            <div class="mt-4 space-y-4">
                <div v-for="row in byService" :key="row.label" class="dashboard-inset-card">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <p class="font-semibold text-slate-900">{{ row.label }}</p>
                        <span class="text-xs font-medium text-slate-500">Conversão {{ Number(row.conversion_rate).toFixed(1) }}%</span>
                    </div>
                    <ProgressBar
                        class="mt-3"
                        label="Orçado (valor do serviço)"
                        :value="(100 * row.budget_total_cents) / maxServiceBudget"
                        :display-value="`${formatBRL(row.budget_total_cents)} · ${row.budget_count} prop.`"
                        bar-class="bg-talents-400"
                    />
                    <ProgressBar
                        class="mt-2"
                        label="Fechado (valor do serviço)"
                        :value="(100 * row.closed_total_cents) / maxServiceClosed"
                        :display-value="`${formatBRL(row.closed_total_cents)} · ${row.closed_count} prop.`"
                        bar-class="bg-talents-700"
                    />
                </div>
                <EmptyState v-if="!byService.length" title="Sem serviços" />
            </div>
        </div>

        <div class="dashboard-panel-static mt-8">
            <div class="flex flex-wrap gap-2 border-b border-rose-100/40 pb-3">
                <button
                    type="button"
                    class="dashboard-tab"
                    :class="listTab === 'recent' ? 'dashboard-tab-active' : 'dashboard-tab-inactive'"
                    @click="listTab = 'recent'"
                >
                    Últimas propostas
                </button>
                <button
                    type="button"
                    class="dashboard-tab"
                    :class="listTab === 'stalled' ? 'dashboard-tab-warn-active' : 'dashboard-tab-inactive'"
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
                        <tr v-for="p in recent" :key="p.id" class="transition hover:bg-talents-50/50">
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
                                    :class="p.is_closed ? 'bg-emerald-100 text-emerald-800' : 'bg-talents-100 text-talents-800'"
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
                        <tr v-for="p in pendingProposals" :key="p.id" class="transition hover:bg-talents-50/50">
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

        <div class="dashboard-panel-static mt-8">
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
                        <tr v-for="row in bySeller" :key="row.seller_id" class="transition hover:bg-talents-50/50">
                            <td class="py-2 pr-3 font-medium text-slate-800">{{ row.name }}</td>
                            <td class="px-3 py-2 text-right tabular-nums text-xs text-slate-600">
                                {{ Number(row.conversion_rate).toFixed(1) }}%
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                <div>{{ formatBRL(row.budget_total_cents) }}</div>
                                <div class="text-xs text-slate-500">{{ row.budget_count }} prop.</div>
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                <div class="text-talents-800">{{ formatBRL(row.closed_total_cents) }}</div>
                                <div class="text-xs text-slate-500">{{ row.closed_count }} fecho(s)</div>
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums text-talents-700">
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
