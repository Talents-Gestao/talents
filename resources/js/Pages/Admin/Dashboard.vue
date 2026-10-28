<script setup>
import '../../../css/admin-home.css';
import AdminQuoteBanner from '@/Components/AdminHome/AdminQuoteBanner.vue';
import LeadsSourceDonut from '@/Components/AdminHome/LeadsSourceDonut.vue';
import MonthlyGoalGauge from '@/Components/AdminHome/MonthlyGoalGauge.vue';
import DashboardSalesFunnel from '@/Components/Dashboard/DashboardSalesFunnel.vue';
import EmptyState from '@/Components/Dashboard/EmptyState.vue';
import ProgressBar from '@/Components/Dashboard/ProgressBar.vue';
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
    ChartPieIcon,
    ClockIcon,
    CurrencyDollarIcon,
    FlagIcon,
    FolderOpenIcon,
    FunnelIcon,
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
        ? 'Volume por etapa · contagens independentes, criados no total'
        : 'Volume por etapa · contagens independentes, criados neste mês',
);

const funnelInfoText =
    'Leads, qualificação, propostas e vendas são contagens independentes do período: não acompanham o mesmo lead.';

const { layout, resetLayout, dragAnimationMs } = useAdminDashboardLayout(userId);

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

const sourceColors = ['#7c3aed', '#2563eb', '#10b981', '#f59e0b', '#ec4899', '#64748b'];

const goalPercent = computed(() => Math.min(100, Number(props.monthlyGoal?.percent || 0)));

const goalSellers = computed(() =>
    Array.isArray(props.monthlyGoal?.sellers) ? props.monthlyGoal.sellers : [],
);

const formatGoalShare = (percent) => {
    const value = Number(percent || 0);
    const rounded = Number.isInteger(value) ? String(value) : value.toFixed(1).replace(/\.0$/, '');
    return `${rounded}% da meta`;
};

const sellerInitial = (name) => String(name || '?').trim().charAt(0).toUpperCase();

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
        hint: `${Math.round(Number(props.kpis.revenue_goal_pct || 0))}% da meta (parcelas pagas)`,
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
        <div class="admin-home space-y-6">
            <header class="min-w-0">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div class="min-w-0">
                        <p class="home-eyebrow"><span class="home-eyebrow__dot" aria-hidden="true" />Home</p>
                        <h1 class="home-title">
                            Painel operacional
                        </h1>
                        <p class="home-greeting">
                            {{ greeting.prefix }}, {{ greeting.first }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="home-btn"
                            title="Repor a ordem padrão das seções e dos cards"
                            @click="resetLayout"
                        >
                            <ArrowPathIcon class="h-4 w-4" aria-hidden="true" />
                            Repor layout padrão
                        </button>
                        <Link
                            v-if="adminTasksOpen > 0"
                            :href="route('admin.tarefas.quadros.index')"
                            class="dashboard-header-cta home-cta group focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-ring"
                        >
                            <span class="dashboard-header-cta-badge">{{ adminTasksOpen }}</span>
                            Tarefas ADM abertas
                        </Link>
                    </div>
                </div>
            </header>

            <!-- Frase do dia: fixa no topo do painel (não entra no layout arrastável) -->
            <AdminQuoteBanner
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
                            class="dashboard-section-drag-handle home-handle mt-1 inline-flex shrink-0 cursor-grab items-center gap-1.5 rounded-xl border px-2.5 py-1.5 text-xs font-semibold active:cursor-grabbing"
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
                                class="home-card home-card--leads dashboard-reveal h-full"
                            >
                                <div class="home-card__head">
                                    <span class="home-chip" aria-hidden="true">
                                        <ChartPieIcon class="h-5 w-5" />
                                    </span>
                                    <div class="min-w-0">
                                        <h3 class="home-card__title">Leads por origem (mês)</h3>
                                        <p class="home-card__sub">De onde vieram os leads deste mês</p>
                                    </div>
                                </div>
                                <LeadsSourceDonut
                                    v-if="leadsSourceTotal > 0"
                                    :rows="leadsBySource"
                                    :colors="sourceColors"
                                />
                                <EmptyState
                                    v-else
                                    class="dashboard-empty-trust mt-4 py-8"
                                    title="Sem leads neste mês"
                                    description="Quando houver envios da landing ou cadastros manuais, as origens aparecem aqui."
                                />
                                <Link
                                    :href="route('admin.landing-interest.index')"
                                    class="home-link group/link"
                                >
                                    Ver leads
                                    <span class="dashboard-panel-link-arrow" aria-hidden="true">→</span>
                                </Link>
                            </section>

                            <section
                                v-else-if="widgetId === 'funnel'"
                                class="home-card home-card--funnel dashboard-reveal h-full"
                            >
                                <div class="home-card__head">
                                    <span class="home-chip" aria-hidden="true">
                                        <FunnelIcon class="h-5 w-5" />
                                    </span>
                                    <div class="min-w-0">
                                        <h3 class="home-card__title">Funil comercial</h3>
                                        <p class="home-card__sub flex items-start gap-1">
                                            <span>{{ funnelScopeLabel }}</span>
                                            <span
                                                class="mt-px inline-flex h-4 w-4 shrink-0 cursor-help items-center justify-center rounded-full border border-ink-subtle text-[10px] font-semibold leading-none text-ink-muted"
                                                role="img"
                                                tabindex="0"
                                                :title="funnelInfoText"
                                                :aria-label="funnelInfoText"
                                            >i</span>
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="home-seg" role="group" aria-label="Período do funil">
                                        <button
                                            type="button"
                                            :aria-pressed="funnelScope === 'month'"
                                            @click="funnelScope = 'month'"
                                        >
                                            Neste mês
                                        </button>
                                        <button
                                            type="button"
                                            :aria-pressed="funnelScope === 'all'"
                                            @click="funnelScope = 'all'"
                                        >
                                            Total
                                        </button>
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
                                class="home-card home-card--goal dashboard-reveal h-full focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-ring"
                                @click="openGoalModal"
                                @keydown.enter.prevent="openGoalModal"
                                @keydown.space.prevent="openGoalModal"
                            >
                                <div class="home-card__head">
                                    <span class="home-chip" aria-hidden="true">
                                        <FlagIcon class="h-5 w-5" />
                                    </span>
                                    <div class="min-w-0">
                                        <h3 class="home-card__title">Meta mensal</h3>
                                        <p class="home-card__sub">Clique para editar a meta</p>
                                    </div>
                                </div>
                                <MonthlyGoalGauge :percent="goalPercent" />
                                <div class="home-goal-total">
                                    <p class="home-goal-total__label">Venda geral</p>
                                    <p class="home-goal-total__value">
                                        {{ formatMoney(monthlyGoal.current_cents) }}
                                    </p>
                                    <p class="home-goal-total__hint">
                                        de {{ formatMoney(monthlyGoal.goal_cents) }} (parcelas pagas de comerciais neste mês)
                                    </p>
                                </div>
                                <div class="home-sellers" @click.stop>
                                    <p class="home-goal-total__label">Por vendedor</p>
                                    <ul
                                        v-if="goalSellers.length"
                                        class="mt-3 max-h-44 space-y-3 overflow-y-auto pr-0.5"
                                    >
                                        <li v-for="seller in goalSellers" :key="seller.id" class="home-seller">
                                            <div class="home-seller__row">
                                                <span class="home-seller__avatar" aria-hidden="true">{{ sellerInitial(seller.name) }}</span>
                                                <span class="home-seller__name">{{ seller.name }}</span>
                                                <span class="home-seller__value">
                                                    {{ formatMoney(seller.current_cents) }} · {{ formatGoalShare(seller.percent) }}
                                                </span>
                                            </div>
                                            <div
                                                class="home-seller__bar"
                                                role="progressbar"
                                                :aria-label="`${seller.name}: ${formatGoalShare(seller.percent)}`"
                                                aria-valuemin="0"
                                                aria-valuemax="100"
                                                :aria-valuenow="Math.round(Math.min(100, Math.max(0, Number(seller.percent) || 0)))"
                                            >
                                                <div
                                                    class="home-seller__fill"
                                                    :style="{ transform: `scaleX(${Math.min(100, Math.max(0, Number(seller.percent) || 0)) / 100})` }"
                                                />
                                            </div>
                                        </li>
                                    </ul>
                                    <p v-else class="mt-2 text-xs text-ink-muted">
                                        Nenhum vendedor comercial para exibir neste mês.
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
                        Defina o alvo de faturamento do mês. O valor atingido soma parcelas pagas neste mês
                        (vendedores comerciais). Pendentes entram no mês em que forem recebidas.
                    </p>

                    <form class="mt-5 space-y-4" @submit.prevent="submitGoal">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                Venda geral (recebido no mês)
                            </p>
                            <p class="mt-1 text-lg font-bold tabular-nums text-slate-900">
                                {{ formatMoney(monthlyGoal.current_cents) }}
                            </p>
                        </div>

                        <div v-if="goalSellers.length" class="space-y-3 border-t border-slate-100 pt-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                Por vendedor
                            </p>
                            <ul class="max-h-56 space-y-3 overflow-y-auto">
                                <li v-for="seller in goalSellers" :key="`modal-seller-${seller.id}`">
                                    <ProgressBar
                                        :label="seller.name"
                                        :value="seller.percent"
                                        :display-value="`${formatMoney(seller.current_cents)} · ${formatGoalShare(seller.percent)}`"
                                        bar-class="bg-violet-600"
                                    />
                                </li>
                            </ul>
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
