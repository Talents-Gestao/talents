<script setup>
import ApexChart from '@/Components/Charts/ApexChart.vue';
import DailyQuoteCard from '@/Components/Dashboard/DailyQuoteCard.vue';
import DashboardSalesFunnel from '@/Components/Dashboard/DashboardSalesFunnel.vue';
import EmptyState from '@/Components/Dashboard/EmptyState.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useDashboardGreeting } from '@/composables/useDashboardGreeting';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    BanknotesIcon,
    BriefcaseIcon,
    BuildingOffice2Icon,
    CalendarDaysIcon,
    ChartBarIcon,
    ClockIcon,
    CurrencyDollarIcon,
    FolderOpenIcon,
    UserPlusIcon,
    ViewColumnsIcon,
} from '@heroicons/vue/24/outline';
import { computed } from 'vue';

const greeting = useDashboardGreeting();
const page = usePage();

const props = defineProps({
    finance: { type: Object, required: true },
    operationToday: { type: Array, default: () => [] },
    tasksToday: { type: Array, default: () => [] },
    adminTasksOpen: { type: Number, default: 0 },
    kpis: { type: Object, required: true },
    leadsBySource: { type: Array, default: () => [] },
    funnel: { type: Array, default: () => [] },
    monthlyGoal: { type: Object, required: true },
});

const dailyQuote = computed(() => page.props.dailyQuote ?? null);

const todayDateLabel = computed(() => {
    const raw = new Date().toLocaleDateString('pt-BR', {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
    return raw.charAt(0).toUpperCase() + raw.slice(1);
});

const formatMoney = (cents) => {
    const value = Number(cents || 0) / 100;
    return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
};

const formatMoneyCompact = (cents) => {
    const value = Number(cents || 0) / 100;
    return value.toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
        maximumFractionDigits: value >= 1000 ? 0 : 2,
    });
};

const forecastPositive = computed(() => Number(props.finance?.forecast_cents || 0) >= 0);

const leadsSourceTotal = computed(() =>
    (props.leadsBySource || []).reduce((sum, row) => sum + Number(row.count || 0), 0),
);

const leadsSourceSeries = computed(() => (props.leadsBySource || []).map((row) => Number(row.count || 0)));

const leadsSourceLabels = computed(() => (props.leadsBySource || []).map((row) => row.label));

const sourceColors = ['#7c3aed', '#2563eb', '#10b981', '#f59e0b', '#ec4899', '#64748b'];

const leadsDonutOptions = computed(() => ({
    chart: { type: 'donut', toolbar: { show: false }, animations: { enabled: true, speed: 400 } },
    labels: leadsSourceLabels.value,
    colors: sourceColors,
    stroke: { width: 0 },
    legend: { show: false },
    plotOptions: {
        pie: {
            donut: {
                size: '72%',
                labels: {
                    show: leadsSourceTotal.value > 0,
                    total: {
                        show: true,
                        label: 'Total',
                        formatter: () => String(leadsSourceTotal.value),
                    },
                },
            },
        },
    },
    dataLabels: { enabled: false },
    tooltip: { y: { formatter: (val) => `${val} leads` } },
}));

const goalPercent = computed(() => Math.min(100, Number(props.monthlyGoal?.percent || 0)));

const goalGaugeOptions = computed(() => ({
    chart: { type: 'radialBar', sparkline: { enabled: true } },
    plotOptions: {
        radialBar: {
            startAngle: -90,
            endAngle: 90,
            hollow: { size: '62%' },
            track: { background: '#e2e8f0', margin: 4 },
            dataLabels: {
                name: { show: true, offsetY: 24, color: '#64748b', fontSize: '12px' },
                value: {
                    show: true,
                    offsetY: -8,
                    fontSize: '28px',
                    fontWeight: 700,
                    color: '#5b21b6',
                    formatter: () => `${Math.round(goalPercent.value)}%`,
                },
            },
        },
    },
    fill: { colors: ['#7c3aed'] },
    labels: ['da meta'],
}));

const kpiCards = computed(() => [
    {
        key: 'clients',
        label: 'Clientes ativos',
        value: props.kpis.active_clients,
        hint: props.kpis.active_clients_delta
            ? `+${props.kpis.active_clients_delta} este mês`
            : 'Empresas ativas',
        icon: BuildingOffice2Icon,
        href: route('admin.companies.index'),
    },
    {
        key: 'new_clients',
        label: 'Novos clientes (mês)',
        value: props.kpis.new_clients_month,
        hint:
            props.kpis.new_clients_delta_pct === 0
                ? 'Vs mês anterior'
                : `${props.kpis.new_clients_delta_pct > 0 ? '+' : ''}${props.kpis.new_clients_delta_pct}% vs mês anterior`,
        icon: UserPlusIcon,
        href: route('admin.companies.index'),
    },
    {
        key: 'mrr',
        label: 'MRR (mensal)',
        value: formatMoneyCompact(props.kpis.mrr_cents),
        hint: 'Soma dos planos das assinaturas ativas',
        icon: CurrencyDollarIcon,
        href: route('admin.plans.index'),
    },
    {
        key: 'revenue',
        label: 'Faturamento (mês)',
        value: formatMoneyCompact(props.kpis.revenue_month_cents),
        hint: `${Math.round(Number(props.kpis.revenue_goal_pct || 0))}% da meta`,
        icon: ChartBarIcon,
        href: route('admin.financeiro.vendas.index'),
    },
    {
        key: 'hiring',
        label: 'Contratações abertas',
        value: props.kpis.hiring_open,
        hint: `${props.kpis.hiring_closed} fechadas`,
        icon: BriefcaseIcon,
        href: route('admin.acompanhamento.index'),
    },
    {
        key: 'hiring_days',
        label: 'Tempo médio contratação',
        value: props.kpis.avg_hiring_days == null ? '—' : `${props.kpis.avg_hiring_days} dias`,
        hint: props.kpis.avg_hiring_days == null ? 'Sem processos fechados' : 'Da abertura à contratação',
        icon: ClockIcon,
        href: route('admin.acompanhamento.index'),
    },
    {
        key: 'methodology',
        label: 'Direcionamento ativo',
        value: props.kpis.methodology_active,
        hint: 'Empresas com metodologia ativa',
        icon: FolderOpenIcon,
        href: route('admin.metodologia.index'),
    },
]);
</script>

<template>
    <Head title="Home" />

    <AdminLayout>
        <div class="space-y-6">
            <header class="min-w-0">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div class="min-w-0">
                        <p class="dashboard-section-label">Operação de hoje</p>
                        <h1 class="dashboard-section-title mt-1 text-xl sm:text-2xl">
                            Painel operacional
                        </h1>
                        <p class="mt-1.5 text-sm font-medium text-slate-800">
                            {{ greeting.prefix }}, {{ greeting.first }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Link
                            v-if="adminTasksOpen > 0"
                            :href="route('admin.tarefas.quadros.index')"
                            class="dashboard-header-cta group"
                        >
                            <span class="dashboard-header-cta-badge">{{ adminTasksOpen }}</span>
                            Tarefas ADM abertas
                        </Link>
                    </div>
                </div>
            </header>

            <DailyQuoteCard v-if="dailyQuote" :quote="dailyQuote" :date-label="todayDateLabel" />

            <!-- Linha principal: Financeiro | Tarefas · hoje | Calendário · hoje -->
            <div class="grid gap-4 xl:grid-cols-3 xl:items-stretch">
                <section class="dashboard-panel dashboard-panel-accent-finance dashboard-reveal flex flex-col">
                    <div class="dashboard-panel-heading">
                        <div>
                            <h3 class="dashboard-panel-title text-emerald-800/80">Financeiro</h3>
                            <p class="dashboard-panel-kicker">Caixa e fluxo do mês</p>
                        </div>
                        <span class="dashboard-panel-icon dashboard-panel-icon-finance" aria-hidden="true">
                            <BanknotesIcon class="h-5 w-5" />
                        </span>
                    </div>
                    <dl class="relative mt-5 grid grid-cols-2 gap-2.5">
                        <div class="dashboard-instrument">
                            <dt class="dashboard-instrument-label">Receber este mês</dt>
                            <dd class="dashboard-instrument-value text-emerald-700">
                                {{ formatMoney(finance.receive_this_month_cents) }}
                            </dd>
                        </div>
                        <div class="dashboard-instrument">
                            <dt class="dashboard-instrument-label">Recebido</dt>
                            <dd class="dashboard-instrument-value text-slate-900">
                                {{ formatMoney(finance.received_cents) }}
                            </dd>
                        </div>
                        <div class="dashboard-instrument">
                            <dt class="dashboard-instrument-label">A receber</dt>
                            <dd class="dashboard-instrument-value text-slate-900">
                                {{ formatMoney(finance.to_receive_cents) }}
                            </dd>
                        </div>
                        <div class="dashboard-instrument hover:!border-rose-200/70">
                            <dt class="dashboard-instrument-label">Contas a pagar</dt>
                            <dd class="dashboard-instrument-value text-rose-600">
                                {{ formatMoney(finance.payables_cents) }}
                            </dd>
                        </div>
                    </dl>
                    <div
                        class="dashboard-forecast-strip relative"
                        :class="{ 'dashboard-forecast-strip-neg': !forecastPositive }"
                    >
                        <span class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-600">
                            Fluxo previsto
                        </span>
                        <span
                            class="text-lg font-bold tabular-nums tracking-tight sm:text-xl"
                            :class="forecastPositive ? 'text-emerald-700' : 'text-rose-600'"
                        >
                            {{ forecastPositive ? '+' : '' }}{{ formatMoney(finance.forecast_cents) }}
                        </span>
                    </div>
                    <div class="relative mt-3 flex w-full items-center justify-between gap-3">
                        <Link
                            :href="route('admin.financeiro.contas-a-pagar.index')"
                            class="dashboard-action-link"
                        >
                            Contas a pagar
                        </Link>
                        <Link
                            :href="route('admin.financeiro.vendas.index')"
                            class="dashboard-action-link"
                        >
                            Contas a receber
                        </Link>
                    </div>
                </section>

                <section
                    class="dashboard-panel dashboard-panel-accent-tasks dashboard-reveal dashboard-reveal-delay-1 flex flex-col"
                >
                    <div class="dashboard-panel-heading">
                        <div>
                            <h3 class="dashboard-panel-title text-talents-800/80">Tarefas · hoje</h3>
                            <p class="dashboard-panel-kicker">Prioridades do dia</p>
                        </div>
                        <span class="dashboard-panel-icon dashboard-panel-icon-tasks" aria-hidden="true">
                            <ViewColumnsIcon class="h-5 w-5" />
                        </span>
                    </div>
                    <ul v-if="tasksToday.length" class="dashboard-calendar-rail relative mt-4">
                        <li
                            v-for="task in tasksToday"
                            :key="task.id"
                            class="dashboard-calendar-item"
                        >
                            <span class="dashboard-calendar-dot" aria-hidden="true" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-slate-900">{{ task.title }}</span>
                                <span
                                    v-if="task.list_name || task.board_name"
                                    class="mt-0.5 block truncate text-xs text-slate-500"
                                >
                                    <template v-if="task.board_name">{{ task.board_name }}</template>
                                    <template v-if="task.board_name && task.list_name"> · </template>
                                    <template v-if="task.list_name">{{ task.list_name }}</template>
                                </span>
                            </span>
                        </li>
                    </ul>
                    <EmptyState
                        v-else
                        class="dashboard-empty-trust relative mt-4 flex-1 py-6"
                        title="Sem tarefas para hoje"
                        description="Quando houver tarefas Admin para o dia, elas aparecem aqui."
                    />
                    <Link
                        :href="route('admin.tarefas.quadros.index')"
                        class="dashboard-panel-link group/link relative mt-auto pt-4"
                    >
                        Ver tarefas
                        <span class="dashboard-panel-link-arrow" aria-hidden="true">→</span>
                    </Link>
                </section>

                <section
                    class="dashboard-panel dashboard-panel-accent-calendar dashboard-reveal dashboard-reveal-delay-2 flex flex-col"
                >
                    <div class="dashboard-panel-heading">
                        <div>
                            <h3 class="dashboard-panel-title text-sky-800/80">Calendário · hoje</h3>
                            <p class="dashboard-panel-kicker">Agenda do dia · {{ todayDateLabel }}</p>
                        </div>
                        <span class="dashboard-panel-icon dashboard-panel-icon-calendar" aria-hidden="true">
                            <CalendarDaysIcon class="h-5 w-5" />
                        </span>
                    </div>
                    <ul v-if="operationToday.length" class="dashboard-calendar-rail relative mt-4">
                        <li
                            v-for="item in operationToday"
                            :key="item.id"
                            class="dashboard-calendar-item"
                        >
                            <span class="dashboard-calendar-dot" aria-hidden="true" />
                            <span class="dashboard-calendar-time">{{ item.time || '—' }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-slate-900">{{ item.title }}</span>
                                <span v-if="item.company_name" class="mt-0.5 block truncate text-xs text-slate-500">
                                    {{ item.company_name }}
                                </span>
                            </span>
                        </li>
                    </ul>
                    <EmptyState
                        v-else
                        class="dashboard-empty-trust relative mt-4 flex-1 py-6"
                        title="Sem agenda para hoje"
                        description="Quando houver itens no calendário estratégico de hoje, eles aparecem aqui."
                    />
                    <Link
                        :href="route('admin.strategic-calendar.index')"
                        class="dashboard-panel-link group/link relative mt-auto pt-4"
                    >
                        Ver agenda completa
                        <span class="dashboard-panel-link-arrow" aria-hidden="true">→</span>
                    </Link>
                </section>
            </div>

            <section class="space-y-3" aria-labelledby="dashboard-kpis-heading">
                <div>
                    <p class="dashboard-section-label">Indicadores</p>
                    <h2 id="dashboard-kpis-heading" class="dashboard-section-title">
                        Visão consolidada
                    </h2>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="(card, index) in kpiCards"
                        :key="card.key"
                        :href="card.href"
                        class="dashboard-panel-compact group dashboard-reveal flex min-h-[7.5rem] flex-col focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-talents-600"
                        :class="{
                            'dashboard-reveal-delay-1': index % 3 === 1,
                            'dashboard-reveal-delay-2': index % 3 === 2,
                            'dashboard-reveal-delay-3': index % 3 === 0 && index > 0,
                        }"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <p class="dashboard-panel-title max-w-[70%] leading-snug">
                                {{ card.label }}
                            </p>
                            <span class="dashboard-kpi-tile" aria-hidden="true">
                                <component :is="card.icon" class="dashboard-kpi-icon" />
                            </span>
                        </div>
                        <p class="dashboard-metric-value mt-auto pt-3">{{ card.value }}</p>
                        <p class="dashboard-metric-hint">{{ card.hint }}</p>
                    </Link>
                </div>
            </section>

            <section class="space-y-3" aria-labelledby="dashboard-insights-heading">
                <div>
                    <p class="dashboard-section-label">Leitura do mês</p>
                    <h2 id="dashboard-insights-heading" class="dashboard-section-title">
                        Origem, funil e meta
                    </h2>
                </div>
                <div class="grid gap-4 lg:grid-cols-3">
                    <section class="dashboard-panel dashboard-reveal">
                        <div class="dashboard-panel-heading mb-1">
                            <h3 class="dashboard-panel-title">Leads por origem (mês)</h3>
                        </div>
                        <div class="mt-4 flex flex-col gap-4">
                            <div class="flex min-h-[200px] w-full justify-center">
                                <ApexChart
                                    v-if="leadsSourceTotal > 0"
                                    type="donut"
                                    height="200"
                                    :options="leadsDonutOptions"
                                    :series="leadsSourceSeries"
                                />
                                <EmptyState
                                    v-else
                                    class="dashboard-empty-trust py-8"
                                    title="Sem leads neste mês"
                                    description="Quando houver envios da landing ou cadastros manuais, as origens aparecem aqui."
                                />
                            </div>
                            <ul
                                v-if="leadsSourceTotal > 0"
                                class="grid w-full grid-cols-1 gap-2 text-sm sm:grid-cols-2"
                            >
                                <li
                                    v-for="(row, idx) in leadsBySource"
                                    :key="row.key"
                                    class="flex items-center justify-between gap-3 rounded-xl border border-violet-100/50 bg-white/80 px-3 py-2"
                                >
                                    <span class="flex min-w-0 items-center gap-2 text-slate-700">
                                        <span
                                            class="h-2.5 w-2.5 shrink-0 rounded-full"
                                            :style="{ backgroundColor: sourceColors[idx % sourceColors.length] }"
                                        />
                                        <span class="whitespace-nowrap">{{ row.label }}</span>
                                    </span>
                                    <span class="shrink-0 tabular-nums font-bold text-slate-900">{{ row.count }}</span>
                                </li>
                            </ul>
                        </div>
                    <Link
                        :href="route('admin.landing-interest.index')"
                        class="dashboard-panel-link group/link mt-4"
                    >
                        Ver leads
                        <span class="dashboard-panel-link-arrow" aria-hidden="true">→</span>
                    </Link>
                </section>

                <section class="dashboard-panel dashboard-reveal dashboard-reveal-delay-1">
                    <div class="dashboard-panel-heading mb-1">
                        <h3 class="dashboard-panel-title">Funil comercial</h3>
                    </div>
                    <div class="mt-4">
                        <DashboardSalesFunnel :funnel="funnel" />
                    </div>
                </section>

                <section class="dashboard-panel dashboard-reveal dashboard-reveal-delay-2">
                    <div class="dashboard-panel-heading mb-1">
                        <h3 class="dashboard-panel-title">Meta mensal</h3>
                    </div>
                    <div class="mt-2 flex min-h-[180px] justify-center">
                        <ApexChart
                            type="radialBar"
                            height="220"
                            :options="goalGaugeOptions"
                            :series="[goalPercent]"
                        />
                    </div>
                    <div class="text-center">
                        <p class="text-xl font-bold tabular-nums tracking-tight text-slate-900">
                            {{ formatMoney(monthlyGoal.current_cents) }}
                        </p>
                        <p class="mt-1 text-xs text-slate-600">
                            de {{ formatMoney(monthlyGoal.goal_cents) }} (vendas do mês)
                        </p>
                    </div>
                </section>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
