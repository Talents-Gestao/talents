<script setup>
import ApexChart from '@/Components/Charts/ApexChart.vue';
import DailyQuoteCard from '@/Components/Dashboard/DailyQuoteCard.vue';
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
    UserGroupIcon,
    UserPlusIcon,
} from '@heroicons/vue/24/outline';
import { computed } from 'vue';

const greeting = useDashboardGreeting();
const page = usePage();

const props = defineProps({
    finance: { type: Object, required: true },
    commercial: { type: Object, required: true },
    operationToday: { type: Array, default: () => [] },
    alertsCount: { type: Number, default: 0 },
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

const funnelMax = computed(() => {
    const counts = (props.funnel || []).map((row) => Number(row.count || 0));
    return Math.max(1, ...counts);
});

const funnelRows = computed(() => {
    const base = Number(props.funnel?.[0]?.count || 0) || funnelMax.value;
    return (props.funnel || []).map((row) => {
        const count = Number(row.count || 0);
        return {
            ...row,
            count,
            pct: base > 0 ? Math.round((100 * count) / base) : 0,
            width: Math.max(8, Math.round((100 * count) / funnelMax.value)),
        };
    });
});

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
                        <p class="text-sm font-medium text-slate-500">
                            {{ greeting.prefix }}, {{ greeting.first }}!
                        </p>
                        <h1 class="mt-1 text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">
                            Painel operacional
                        </h1>
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
                        <Link
                            v-if="alertsCount > 0"
                            :href="route('admin.notices.index')"
                            class="dashboard-header-cta group"
                        >
                            <span class="dashboard-header-cta-badge">{{ alertsCount }}</span>
                            Pendências
                        </Link>
                    </div>
                </div>
            </header>

            <DailyQuoteCard v-if="dailyQuote" :quote="dailyQuote" :date-label="todayDateLabel" />

            <!-- Linha principal: Financeiro | Comercial | Calendário · hoje -->
            <div class="grid gap-4 xl:grid-cols-3 xl:items-stretch">
                <section class="dashboard-panel dashboard-panel-accent-finance dashboard-reveal flex flex-col">
                    <div class="dashboard-panel-heading">
                        <div>
                            <h3 class="dashboard-panel-title text-emerald-800/70">Financeiro</h3>
                            <p class="mt-1 text-sm font-medium text-slate-600">Caixa e fluxo do mês</p>
                        </div>
                        <span class="dashboard-panel-icon dashboard-panel-icon-finance">
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
                        <span class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">
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
                    class="dashboard-panel dashboard-panel-accent-commercial dashboard-reveal dashboard-reveal-delay-1 flex flex-col"
                >
                    <div class="dashboard-panel-heading">
                        <div>
                            <h3 class="dashboard-panel-title text-talents-800/70">Comercial</h3>
                            <p class="mt-1 text-sm font-medium text-slate-600">Pipeline do mês</p>
                        </div>
                        <span class="dashboard-panel-icon dashboard-panel-icon-commercial">
                            <UserGroupIcon class="h-5 w-5" />
                        </span>
                    </div>
                    <ul class="relative mt-5 space-y-2">
                        <li class="dashboard-pipeline-row">
                            <span class="text-sm text-slate-600">Leads novos</span>
                            <span class="text-base font-bold tabular-nums tracking-tight text-slate-900">
                                {{ commercial.leads_new }}
                            </span>
                        </li>
                        <li class="dashboard-pipeline-row">
                            <span class="text-sm text-slate-600">Propostas enviadas</span>
                            <span class="text-base font-bold tabular-nums tracking-tight text-slate-900">
                                {{ commercial.proposals_sent }}
                            </span>
                        </li>
                        <li
                            class="dashboard-pipeline-row"
                            :class="{ 'dashboard-pipeline-row-hot': commercial.in_negotiation > 0 }"
                        >
                            <span class="text-sm font-medium text-slate-700">Em negociação</span>
                            <span
                                class="text-base font-bold tabular-nums tracking-tight"
                                :class="commercial.in_negotiation > 0 ? 'text-talents-800' : 'text-slate-900'"
                            >
                                {{ commercial.in_negotiation }}
                            </span>
                        </li>
                        <li class="dashboard-pipeline-row">
                            <span class="text-sm text-slate-600">Fechadas</span>
                            <span class="text-base font-bold tabular-nums tracking-tight text-slate-900">
                                {{ commercial.closed }}
                            </span>
                        </li>
                    </ul>
                    <div class="relative mt-4 grid grid-cols-2 gap-2.5">
                        <div class="dashboard-twin-tile">
                            <p class="dashboard-instrument-label">Taxa de conversão</p>
                            <p class="mt-2 text-xl font-bold tabular-nums tracking-tight text-talents-800">
                                {{ commercial.conversion_rate }}%
                            </p>
                        </div>
                        <div class="dashboard-twin-tile">
                            <p class="dashboard-instrument-label">Ticket médio</p>
                            <p class="mt-2 text-xl font-bold tabular-nums tracking-tight text-slate-900">
                                {{ formatMoneyCompact(commercial.avg_ticket_cents) }}
                            </p>
                        </div>
                    </div>
                    <Link
                        :href="route('admin.comercial.propostas.index')"
                        class="dashboard-panel-link group/link relative mt-auto pt-4"
                    >
                        Ver Comercial
                        <span class="dashboard-panel-link-arrow" aria-hidden="true">→</span>
                    </Link>
                </section>

                <section
                    class="dashboard-panel dashboard-panel-accent-calendar dashboard-reveal dashboard-reveal-delay-2 flex flex-col"
                >
                    <div class="dashboard-panel-heading">
                        <div>
                            <h3 class="dashboard-panel-title text-sky-800/70">Calendário · hoje</h3>
                            <p class="mt-1 text-sm font-medium text-slate-600">Agenda do dia</p>
                        </div>
                        <span class="dashboard-panel-icon dashboard-panel-icon-calendar">
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
                        class="relative mt-4 flex-1 !rounded-2xl !border-sky-100/50 !bg-sky-50/30 py-6"
                        title="Sem agenda para hoje"
                        description="Itens do calendário estratégico de hoje aparecem aqui."
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

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="(card, index) in kpiCards"
                    :key="card.key"
                    :href="card.href"
                    class="dashboard-panel-compact group dashboard-reveal flex min-h-[7.5rem] flex-col"
                    :class="{
                        'dashboard-reveal-delay-1': index % 3 === 1,
                        'dashboard-reveal-delay-2': index % 3 === 2,
                        'dashboard-reveal-delay-3': index % 3 === 0 && index > 0,
                    }"
                >
                    <div class="flex items-start justify-between gap-3">
                        <p class="dashboard-panel-title max-w-[70%] leading-snug text-slate-500">
                            {{ card.label }}
                        </p>
                        <span class="dashboard-kpi-tile">
                            <component :is="card.icon" class="dashboard-kpi-icon" />
                        </span>
                    </div>
                    <p class="dashboard-metric-value mt-auto pt-3">{{ card.value }}</p>
                    <p class="dashboard-metric-hint">{{ card.hint }}</p>
                </Link>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <section class="dashboard-panel dashboard-reveal">
                    <div class="dashboard-panel-heading mb-1">
                        <h3 class="dashboard-panel-title">Leads por origem (mês)</h3>
                    </div>
                    <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-center">
                        <div class="flex min-h-[200px] flex-1 justify-center">
                            <ApexChart
                                v-if="leadsSourceTotal > 0"
                                type="donut"
                                height="200"
                                :options="leadsDonutOptions"
                                :series="leadsSourceSeries"
                            />
                            <EmptyState
                                v-else
                                class="!rounded-2xl !border-rose-100/40 !bg-rose-50/20 py-8"
                                title="Sem leads neste mês"
                                description="Origens vêm dos formulários da landing e do cadastro manual."
                            />
                        </div>
                        <ul v-if="leadsSourceTotal > 0" class="w-full space-y-2 text-sm sm:w-40">
                            <li
                                v-for="(row, idx) in leadsBySource"
                                :key="row.key"
                                class="dashboard-pipeline-row !py-2"
                            >
                                <span class="flex items-center gap-2 text-slate-600">
                                    <span
                                        class="h-2 w-2 rounded-full"
                                        :style="{ backgroundColor: sourceColors[idx % sourceColors.length] }"
                                    />
                                    {{ row.label }}
                                </span>
                                <span class="tabular-nums font-bold text-slate-900">{{ row.count }}</span>
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
                    <ul class="mt-5 space-y-3.5">
                        <li v-for="row in funnelRows" :key="row.key">
                            <div class="mb-1.5 flex items-center justify-between gap-2 text-sm">
                                <span class="font-semibold text-slate-700">{{ row.label }}</span>
                                <span class="tabular-nums text-slate-500">{{ row.count }} · {{ row.pct }}%</span>
                            </div>
                            <div class="h-2.5 overflow-hidden rounded-full bg-violet-100/60">
                                <div
                                    class="h-full rounded-full bg-gradient-to-r from-talents-700 to-violet-400 transition-all"
                                    :style="{ width: `${row.width}%` }"
                                />
                            </div>
                        </li>
                    </ul>
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
                        <p class="mt-1 text-xs text-slate-500">
                            de {{ formatMoney(monthlyGoal.goal_cents) }} (vendas do mês)
                        </p>
                    </div>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
