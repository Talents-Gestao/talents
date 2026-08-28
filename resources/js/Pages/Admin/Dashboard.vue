<script setup>
import ApexChart from '@/Components/Charts/ApexChart.vue';
import DailyQuoteCard from '@/Components/Dashboard/DailyQuoteCard.vue';
import DashboardSalesFunnel from '@/Components/Dashboard/DashboardSalesFunnel.vue';
import EmptyState from '@/Components/Dashboard/EmptyState.vue';
import FullScreenOverlay from '@/Components/FullScreenOverlay.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useAdminDashboardLayout } from '@/composables/useAdminDashboardLayout';
import { useDashboardGreeting } from '@/composables/useDashboardGreeting';
import { centsToMoneyModel } from '@/utils/moneyMask';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowPathIcon,
    BanknotesIcon,
    Bars3Icon,
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
import { computed, ref } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';

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
    funnelLost: {
        type: Object,
        default: () => ({
            count: 0,
            href: null,
            items: [],
        }),
    },
    funnelAll: { type: Array, default: () => [] },
    funnelLostAll: {
        type: Object,
        default: () => ({
            count: 0,
            href: null,
            items: [],
        }),
    },
    monthlyGoal: { type: Object, required: true },
});

const dailyQuote = computed(() => page.props.dailyQuote ?? null);
const userId = computed(() => page.props.auth?.user?.id ?? null);

/** Visão do funil: mês corrente ou histórico completo. */
const funnelScope = ref('month');

const activeFunnel = computed(() =>
    funnelScope.value === 'all' ? (props.funnelAll || []) : (props.funnel || []),
);

const activeFunnelLost = computed(() =>
    funnelScope.value === 'all' ? (props.funnelLostAll || {}) : (props.funnelLost || {}),
);

const funnelScopeLabel = computed(() =>
    funnelScope.value === 'all'
        ? 'Todas as propostas (histórico)'
        : 'Propostas criadas neste mês',
);

const { layout, resetLayout, dragAnimationMs, prefersReducedMotion } = useAdminDashboardLayout(userId);

/**
 * Auto-scroll do Sortable aponta ao `<main class="app-shell-main-scroll">`
 * (SidebarLayout) via detecção de overflow + bubbleScroll.
 */
const sortableScrollProps = {
    scroll: true,
    bubbleScroll: true,
    forceAutoScrollFallback: true,
    scrollSensitivity: 64,
    scrollSpeed: 22,
};

const sectionMeta = {
    operation: {
        label: 'Operação de hoje',
        title: 'Caixa, tarefas e agenda',
        headingId: 'dashboard-operation-heading',
        gridClass: 'grid gap-4 xl:grid-cols-3 xl:items-stretch',
    },
    kpis: {
        label: 'Indicadores',
        title: 'Visão consolidada',
        headingId: 'dashboard-kpis-heading',
        gridClass: 'grid gap-3 sm:grid-cols-2 lg:grid-cols-3',
    },
    insights: {
        label: 'Leitura do mês',
        title: 'Origem, funil e meta',
        headingId: 'dashboard-insights-heading',
        gridClass: 'grid gap-4 lg:grid-cols-3',
    },
};

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
    chart: {
        type: 'donut',
        toolbar: { show: false },
        animations: { enabled: !prefersReducedMotion.value, speed: 400 },
    },
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
    chart: {
        type: 'radialBar',
        sparkline: { enabled: true },
        animations: { enabled: !prefersReducedMotion.value },
    },
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

const kpiById = computed(() => ({
    active_clients: {
        id: 'active_clients',
        label: 'Clientes ativos',
        value: props.kpis.active_clients,
        hint: props.kpis.active_clients_delta
            ? `+${props.kpis.active_clients_delta} este mês`
            : 'Empresas ativas',
        icon: BuildingOffice2Icon,
        href: route('admin.companies.index'),
    },
    new_clients: {
        id: 'new_clients',
        label: 'Novos clientes (mês)',
        value: props.kpis.new_clients_month,
        hint:
            props.kpis.new_clients_delta_pct === 0
                ? 'Vs mês anterior'
                : `${props.kpis.new_clients_delta_pct > 0 ? '+' : ''}${props.kpis.new_clients_delta_pct}% vs mês anterior`,
        icon: UserPlusIcon,
        href: route('admin.companies.index'),
    },
    mrr: {
        id: 'mrr',
        label: 'MRR (mensal)',
        value: formatMoneyCompact(props.kpis.mrr_cents),
        hint: 'Soma dos planos das assinaturas ativas',
        icon: CurrencyDollarIcon,
        href: route('admin.plans.index'),
    },
    revenue: {
        id: 'revenue',
        label: 'Faturamento (mês)',
        value: formatMoneyCompact(props.kpis.revenue_month_cents),
        hint: `${Math.round(Number(props.kpis.revenue_goal_pct || 0))}% da meta`,
        icon: ChartBarIcon,
        href: route('admin.financeiro.vendas.index'),
    },
    hiring: {
        id: 'hiring',
        label: 'Contratações abertas',
        value: props.kpis.hiring_open,
        hint: `${props.kpis.hiring_closed} fechadas`,
        icon: BriefcaseIcon,
        href: route('admin.acompanhamento.index'),
    },
    hiring_days: {
        id: 'hiring_days',
        label: 'Tempo médio contratação',
        value: props.kpis.avg_hiring_days == null ? '—' : `${props.kpis.avg_hiring_days} dias`,
        hint: props.kpis.avg_hiring_days == null ? 'Sem processos fechados' : 'Da abertura à contratação',
        icon: ClockIcon,
        href: route('admin.acompanhamento.index'),
    },
    methodology: {
        id: 'methodology',
        label: 'Direcionamento ativo',
        value: props.kpis.methodology_active,
        hint: 'Empresas com metodologia ativa',
        icon: FolderOpenIcon,
        href: route('admin.metodologia.index'),
    },
}));

const goalModalOpen = ref(false);
const goalForm = useForm({
    goal_reais: '',
});

const openGoalModal = () => {
    const cents = Number(props.monthlyGoal?.goal_cents || 0);
    goalForm.goal_reais = centsToMoneyModel(cents);
    goalForm.clearErrors();
    goalModalOpen.value = true;
};

const closeGoalModal = () => {
    if (goalForm.processing) {
        return;
    }
    goalModalOpen.value = false;
};

const submitGoal = () => {
    goalForm.patch(route('admin.dashboard.monthly-goal.update'), {
        preserveScroll: true,
        onSuccess: () => {
            goalModalOpen.value = false;
        },
    });
};
</script>

<template>
    <Head title="Home" />

    <AdminLayout>
        <div class="space-y-6">
            <header class="min-w-0">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div class="min-w-0">
                        <p class="dashboard-section-label">Home</p>
                        <h1 class="dashboard-section-title mt-1 text-xl sm:text-2xl">
                            Painel operacional
                        </h1>
                        <p class="mt-1.5 text-sm font-medium text-slate-800">
                            {{ greeting.prefix }}, {{ greeting.first }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                            title="Repor a ordem padrão das seções e dos cards"
                            @click="resetLayout"
                        >
                            <ArrowPathIcon class="h-4 w-4" aria-hidden="true" />
                            Repor layout padrão
                        </button>
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

            <!-- Frase do dia: fixa no topo do painel (não entra no layout arrastável) -->
            <DailyQuoteCard
                v-if="dailyQuote"
                :quote="dailyQuote"
                :date-label="todayDateLabel"
            />

            <VueDraggable
                v-model="layout.sectionOrder"
                handle=".dashboard-section-drag-handle"
                :animation="dragAnimationMs"
                ghost-class="opacity-40"
                class="space-y-6"
                direction="vertical"
                :scroll="sortableScrollProps.scroll"
                :bubble-scroll="sortableScrollProps.bubbleScroll"
                :force-auto-scroll-fallback="sortableScrollProps.forceAutoScrollFallback"
                :scroll-sensitivity="sortableScrollProps.scrollSensitivity"
                :scroll-speed="sortableScrollProps.scrollSpeed"
                :invert-swap="true"
                :swap-threshold="0.65"
            >
                <section
                    v-for="sectionId in layout.sectionOrder"
                    :key="sectionId"
                    class="space-y-3"
                    :aria-labelledby="sectionMeta[sectionId].headingId"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="dashboard-section-label">{{ sectionMeta[sectionId].label }}</p>
                            <h2
                                :id="sectionMeta[sectionId].headingId"
                                class="dashboard-section-title"
                            >
                                {{ sectionMeta[sectionId].title }}
                            </h2>
                        </div>
                        <button
                            type="button"
                            class="dashboard-section-drag-handle mt-1 inline-flex shrink-0 cursor-grab items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-500 shadow-sm transition hover:border-slate-300 hover:text-slate-700 active:cursor-grabbing"
                            title="Arrastar seção"
                            aria-label="Arrastar seção"
                        >
                            <Bars3Icon class="h-4 w-4" aria-hidden="true" />
                            Mover seção
                        </button>
                    </div>

                    <VueDraggable
                        v-model="layout.sections[sectionId]"
                        group="dashboard-widgets"
                        handle=".dashboard-widget-drag-handle"
                        :animation="dragAnimationMs"
                        ghost-class="opacity-40"
                        :class="[
                            sectionMeta[sectionId].gridClass,
                            layout.sections[sectionId].length === 0
                                ? 'min-h-[6.5rem] rounded-2xl border border-dashed border-slate-200 bg-slate-50/70'
                                : '',
                        ]"
                        :scroll="sortableScrollProps.scroll"
                        :bubble-scroll="sortableScrollProps.bubbleScroll"
                        :force-auto-scroll-fallback="sortableScrollProps.forceAutoScrollFallback"
                        :scroll-sensitivity="sortableScrollProps.scrollSensitivity"
                        :scroll-speed="sortableScrollProps.scrollSpeed"
                    >
                        <div
                            v-for="widgetId in layout.sections[sectionId]"
                            :key="widgetId"
                            class="relative"
                            :class="kpiById[widgetId] ? '' : 'flex h-full min-h-0 flex-col'"
                        >
                            <button
                                type="button"
                                class="dashboard-widget-drag-handle absolute z-20 cursor-grab rounded-lg border border-slate-200/80 bg-white/90 p-1 text-slate-400 shadow-sm transition hover:border-slate-300 hover:text-slate-600 active:cursor-grabbing"
                                :class="kpiById[widgetId] ? 'right-2 top-2' : 'right-3 top-3'"
                                title="Arrastar para outra seção ou reordenar"
                                :aria-label="kpiById[widgetId] ? 'Arrastar indicador' : 'Arrastar card'"
                                @click.stop
                            >
                                <Bars3Icon
                                    :class="kpiById[widgetId] ? 'h-3.5 w-3.5' : 'h-4 w-4'"
                                    aria-hidden="true"
                                />
                            </button>

                            <section
                                v-if="widgetId === 'finance'"
                                class="dashboard-panel dashboard-panel-accent-finance dashboard-reveal flex h-full flex-col"
                            >
                                    <div class="dashboard-panel-heading pr-10">
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
                                            :href="route('admin.financeiro.contas-a-receber.index')"
                                            class="dashboard-action-link"
                                        >
                                            Contas a receber
                                        </Link>
                                    </div>
                                </section>

                                <section
                                    v-else-if="widgetId === 'tasks_today'"
                                    class="dashboard-panel dashboard-panel-accent-tasks dashboard-reveal flex h-full flex-col"
                                >
                                    <div class="dashboard-panel-heading pr-10">
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
                                        title="Sem tarefas atribuídas a si para hoje"
                                        description="Quando houver tarefas Admin com vencimento hoje (ou atrasadas) e o seu nome no cartão, elas aparecem aqui."
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
                                    v-else-if="widgetId === 'calendar_today'"
                                    class="dashboard-panel dashboard-panel-accent-calendar dashboard-reveal flex h-full flex-col"
                                >
                                    <div class="dashboard-panel-heading pr-10">
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

                            <Link
                                v-else-if="kpiById[widgetId]"
                                :href="kpiById[widgetId].href"
                                class="dashboard-panel-compact group dashboard-reveal flex min-h-[7.5rem] flex-col focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-talents-600"
                            >
                                <div class="flex items-start justify-between gap-3 pr-8">
                                    <p class="dashboard-panel-title max-w-[70%] leading-snug">
                                        {{ kpiById[widgetId].label }}
                                    </p>
                                    <span class="dashboard-kpi-tile" aria-hidden="true">
                                        <component :is="kpiById[widgetId].icon" class="dashboard-kpi-icon" />
                                    </span>
                                </div>
                                <p class="dashboard-metric-value mt-auto pt-3">{{ kpiById[widgetId].value }}</p>
                                <p class="dashboard-metric-hint">{{ kpiById[widgetId].hint }}</p>
                            </Link>

                            <section
                                v-else-if="widgetId === 'leads_source'"
                                class="dashboard-panel dashboard-reveal h-full"
                            >
                                    <div class="dashboard-panel-heading mb-1 pr-10">
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

                                <section
                                    v-else-if="widgetId === 'funnel'"
                                    class="dashboard-panel dashboard-reveal h-full"
                                >
                                    <div class="dashboard-panel-heading mb-1 pr-10">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <h3 class="dashboard-panel-title">Funil comercial</h3>
                                                <p class="mt-0.5 text-xs text-slate-500">{{ funnelScopeLabel }}</p>
                                            </div>
                                            <div
                                                class="inline-flex shrink-0 rounded-lg border border-slate-200 bg-slate-50 p-0.5"
                                                role="group"
                                                aria-label="Período do funil"
                                            >
                                                <button
                                                    type="button"
                                                    class="rounded-md px-2.5 py-1 text-[11px] font-semibold transition"
                                                    :class="
                                                        funnelScope === 'month'
                                                            ? 'bg-white text-talents-800 shadow-sm'
                                                            : 'text-slate-500 hover:text-slate-700'
                                                    "
                                                    :aria-pressed="funnelScope === 'month'"
                                                    @click="funnelScope = 'month'"
                                                >
                                                    Neste mês
                                                </button>
                                                <button
                                                    type="button"
                                                    class="rounded-md px-2.5 py-1 text-[11px] font-semibold transition"
                                                    :class="
                                                        funnelScope === 'all'
                                                            ? 'bg-white text-talents-800 shadow-sm'
                                                            : 'text-slate-500 hover:text-slate-700'
                                                    "
                                                    :aria-pressed="funnelScope === 'all'"
                                                    @click="funnelScope = 'all'"
                                                >
                                                    Total
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-4">
                                        <DashboardSalesFunnel
                                            :funnel="activeFunnel"
                                            :lost="activeFunnelLost"
                                            :scope="funnelScope"
                                        />
                                    </div>
                                </section>

                                <section
                                    v-else-if="widgetId === 'monthly_goal'"
                                    role="button"
                                    tabindex="0"
                                    title="Editar meta mensal"
                                    aria-label="Editar meta mensal"
                                    class="dashboard-panel dashboard-reveal h-full cursor-pointer transition hover:border-violet-200/80 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-talents-600"
                                    @click="openGoalModal"
                                    @keydown.enter.prevent="openGoalModal"
                                    @keydown.space.prevent="openGoalModal"
                                >
                                    <div class="dashboard-panel-heading mb-1 pr-10">
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
                                            de {{ formatMoney(monthlyGoal.goal_cents) }} (vendas fechadas no mês; recorrente = 1 parcela)
                                        </p>
                                    </div>
                                </section>
                        </div>
                    </VueDraggable>
                </section>
            </VueDraggable>

            <FullScreenOverlay :show="goalModalOpen" @close="closeGoalModal">
                <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                    <h3 class="text-lg font-semibold text-slate-900">Meta mensal</h3>
                    <p class="mt-1 text-sm text-slate-600">
                        Defina o alvo de faturamento do mês. O valor atingido soma vendas com data de venda neste mês
                        (recorrentes entram só com a parcela mensal, não o total do período).
                    </p>

                    <form class="mt-5 space-y-4" @submit.prevent="submitGoal">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                Valor atingido (vendas fechadas no mês)
                            </p>
                            <p class="mt-1 text-lg font-bold tabular-nums text-slate-900">
                                {{ formatMoney(monthlyGoal.current_cents) }}
                            </p>
                        </div>

                        <div>
                            <InputLabel for="goal_reais" value="Meta (R$)" />
                            <MoneyInput
                                id="goal_reais"
                                v-model="goalForm.goal_reais"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError class="mt-1" :message="goalForm.errors.goal_reais" />
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <SecondaryButton type="button" :disabled="goalForm.processing" @click="closeGoalModal">
                                Cancelar
                            </SecondaryButton>
                            <PrimaryButton type="submit" :disabled="goalForm.processing">
                                {{ goalForm.processing ? 'Salvando…' : 'Salvar' }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </FullScreenOverlay>
        </div>
    </AdminLayout>
</template>
