<script setup>
import EmptyState from '@/Components/Dashboard/EmptyState.vue';
import HealthBadge from '@/Components/Dashboard/HealthBadge.vue';
import ProgressBar from '@/Components/Dashboard/ProgressBar.vue';
import SectionHeader from '@/Components/Dashboard/SectionHeader.vue';
import StatCard from '@/Components/Dashboard/StatCard.vue';
import StrategicCalendarWidget from '@/Components/StrategicCalendarWidget.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useDashboardGreeting } from '@/composables/useDashboardGreeting';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const greeting = useDashboardGreeting();

const props = defineProps({
    stats: Object,
    riskBySegment: Array,
    riskDistribution: Object,
    criticalCompanies: Array,
    pendingComplaints: Array,
    recentLeads: Array,
    upcomingCalendar: Array,
    subscriptionsDueSoon: Array,
    dashboardCalendar: Object,
});

const todayLabel = computed(() =>
    new Date().toLocaleDateString('pt-BR', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }),
);

const responsesSpark = computed(() => (props.stats?.responses_sparkline || []).map((p) => p.count));
const complaintsSpark = computed(() => (props.stats?.complaints_sparkline || []).map((p) => p.count));

const riskDonutTotal = computed(() => {
    const d = props.riskDistribution || {};
    return (d.green || 0) + (d.yellow || 0) + (d.red || 0);
});

const riskDonutSeries = computed(() => {
    const d = props.riskDistribution || {};
    return [d.green || 0, d.yellow || 0, d.red || 0];
});

const riskDonutOptions = computed(() => ({
    chart: { type: 'donut', toolbar: { show: false }, animations: { enabled: true, speed: 400 } },
    labels: ['Saudável', 'Atenção', 'Crítico'],
    colors: ['#10b981', '#f59e0b', '#f43f5e'],
    stroke: { width: 0 },
    legend: { show: false },
    plotOptions: {
        pie: {
            donut: {
                size: '72%',
                labels: {
                    show: riskDonutTotal.value > 0,
                    total: {
                        show: true,
                        label: 'Empresas',
                        formatter: () => String(riskDonutTotal.value),
                    },
                },
            },
        },
    },
    dataLabels: { enabled: false },
    tooltip: { theme: 'light', y: { formatter: (val) => `${val} empresas` } },
}));

const maxSegmentScore = computed(() => {
    const rows = props.riskBySegment || [];
    if (!rows.length) return 100;
    return Math.max(100, ...rows.map((r) => Number(r.avg_score) || 0));
});

const formatShortDate = (iso) => {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' });
    } catch {
        return '—';
    }
};

const userInitials = computed(() => {
    const full = greeting.value.full || '';
    const parts = full.split(/\s+/).filter(Boolean);
    if (parts.length >= 2) {
        return `${parts[0][0] ?? ''}${parts[1][0] ?? ''}`.toUpperCase();
    }
    return (parts[0]?.slice(0, 2) || 'TA').toUpperCase();
});

const nextCalendarEvent = computed(() => {
    const items = props.upcomingCalendar;
    if (!items?.length) return null;
    return items[0];
});

const formatEventLong = (item) => {
    if (!item?.occurs_on) return '';
    try {
        return new Date(item.occurs_on).toLocaleDateString('pt-BR', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });
    } catch {
        return '';
    }
};

const riskLegend = computed(() => {
    const d = props.riskDistribution || {};
    return [
        { key: 'green', label: 'Saudável', count: d.green || 0, color: 'bg-emerald-500' },
        { key: 'yellow', label: 'Atenção', count: d.yellow || 0, color: 'bg-amber-500' },
        { key: 'red', label: 'Crítico', count: d.red || 0, color: 'bg-rose-500' },
    ];
});

const criticalCount = computed(() => props.criticalCompanies?.length ?? 0);
</script>

<template>
    <Head title="Painel Admin" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Painel executivo</p>
                    <h2 class="mt-0.5 text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">Visão geral Talents</h2>
                    <p class="mt-1 text-sm capitalize text-slate-500">{{ todayLabel }}</p>
                </div>
                <Link
                    v-if="Number(stats.pending_complaints_total) > 0"
                    :href="route('admin.companies.index')"
                    class="dashboard-header-cta group"
                >
                    <span class="dashboard-header-cta-badge">
                        {{ stats.pending_complaints_total }}
                    </span>
                    Denúncias pendentes
                </Link>
            </div>
        </template>

        <template #aside>
            <div class="space-y-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Atalhos</p>
                <div class="surface-glass space-y-3 p-4 text-sm text-slate-700">
                    <Link :href="route('admin.companies.index')" class="block font-medium text-talents-800 hover:underline">
                        Empresas
                    </Link>
                    <Link :href="route('admin.settings.edit')" class="block font-medium text-talents-800 hover:underline">
                        Configurações
                    </Link>
                    <Link :href="route('admin.landing-interest.index')" class="block font-medium text-talents-800 hover:underline">
                        Interessados
                    </Link>
                </div>
            </div>
        </template>

        <!-- Hero + status (estilo cartões principais) -->
        <div class="mb-8 grid gap-4 lg:grid-cols-3">
            <div class="dashboard-hero lg:col-span-2">
                <div class="dashboard-hero-blob -right-16 -top-16 h-48 w-48" />
                <div class="dashboard-hero-blob-accent -bottom-20 left-1/4 h-40 w-40" />
                <div class="relative flex flex-wrap items-start justify-between gap-6">
                    <div class="flex min-w-0 flex-1 gap-4">
                        <div
                            class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/20 text-lg font-bold tracking-tight text-white ring-2 ring-white/30 backdrop-blur-sm"
                            aria-hidden="true"
                        >
                            {{ userInitials }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-white/85">
                                {{ greeting.prefix }}, <span class="font-bold text-white">{{ greeting.first }}</span>
                            </p>
                            <template v-if="nextCalendarEvent">
                                <p class="mt-3 text-xs font-semibold uppercase tracking-wider text-white/70">Próximo no calendário</p>
                                <h3 class="mt-1 text-xl font-bold leading-snug sm:text-2xl">{{ nextCalendarEvent.title }}</h3>
                                <p class="mt-2 text-sm text-white/90">{{ formatEventLong(nextCalendarEvent) }}</p>
                                <p v-if="nextCalendarEvent.company" class="mt-1 text-xs text-white/75">
                                    {{ nextCalendarEvent.company.name }}
                                </p>
                            </template>
                            <template v-else>
                                <h3 class="mt-3 text-xl font-bold leading-snug sm:text-2xl">Sem eventos nos próximos 7 dias</h3>
                                <p class="mt-2 max-w-md text-sm text-white/85">Planeje ritos e marcos no calendário estratégico para a equipa ver aqui o próximo passo.</p>
                            </template>
                        </div>
                    </div>
                    <Link
                        :href="route('admin.strategic-calendar.index')"
                        class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white text-talents-700 shadow-lg transition hover:scale-105 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-white/80"
                        aria-label="Abrir calendário estratégico"
                    >
                        <svg class="h-5 w-5 translate-x-px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </Link>
                </div>
            </div>

            <div class="dashboard-accent-dark text-white">
                <div class="dashboard-hero-blob right-0 top-0 h-32 w-32 translate-x-1/3 -translate-y-1/3 bg-talents-500/25" />
                <div class="relative">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Resumo rápido</p>
                    <h3 class="mt-2 text-lg font-bold">Alertas NR-1</h3>
                    <div class="mt-5 space-y-4">
                        <div class="flex items-center justify-between gap-2 rounded-2xl bg-white/5 px-3 py-2.5 ring-1 ring-white/10">
                            <span class="text-sm text-slate-300">Empresas críticas</span>
                            <span class="text-2xl font-bold tabular-nums text-rose-400">{{ criticalCount }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-2 rounded-2xl bg-white/5 px-3 py-2.5 ring-1 ring-white/10">
                            <span class="text-sm text-slate-300">Denúncias abertas</span>
                            <span class="text-2xl font-bold tabular-nums text-amber-300">{{ stats.pending_complaints_total }}</span>
                        </div>
                    </div>
                </div>
                <Link
                    :href="route('admin.companies.index')"
                    class="relative mt-6 inline-flex w-full items-center justify-center rounded-xl bg-white/10 py-2.5 text-sm font-semibold text-white ring-1 ring-white/20 transition hover:bg-white/20"
                >
                    Ver empresas
                </Link>
            </div>
        </div>

        <!-- KPIs -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                label="Empresas ativas"
                :value="stats.companies_active"
                :hint="`de ${stats.companies_total} cadastradas`"
                :detail-href="route('admin.companies.index')"
                detail-label="Gerir empresas"
            />
            <StatCard
                label="Empresas em campanha ativa"
                :value="stats.companies_with_active_campaign"
                hint="Com pesquisa NR-1 em andamento"
                :detail-href="route('admin.companies.index')"
                detail-label="Ver empresas"
            />
            <StatCard
                label="Taxa de conclusão"
                :value="`${Number(stats.completion_rate).toFixed(1)}%`"
                :hint="`${stats.responses_completed} de ${stats.responses_total} respostas`"
                :sparkline-series="responsesSpark"
                :detail-href="route('admin.companies.index')"
                detail-label="Explorar"
            />
            <StatCard
                label="Denúncias pendentes"
                :value="stats.pending_complaints_total"
                hint="Novas ou em análise"
                :sparkline-series="complaintsSpark"
                :detail-href="route('admin.companies.index')"
                detail-label="Empresas"
            />
        </div>

        <!-- Zona principal + lateral -->
        <div class="mt-8 grid gap-8 lg:grid-cols-3">
            <div class="space-y-8 lg:col-span-2">
                <div class="dashboard-panel">
                    <SectionHeader
                        variant="panel"
                        title="Saúde organizacional"
                        subtitle="Distribuição na última campanha de cada empresa (resultado global)"
                    />
                    <div class="mt-6 flex flex-col gap-8 lg:flex-row lg:items-start">
                        <div class="flex min-h-[220px] min-w-0 flex-1 justify-center">
                            <apexchart
                                v-if="riskDonutTotal > 0"
                                type="donut"
                                height="260"
                                :options="riskDonutOptions"
                                :series="riskDonutSeries"
                            />
                            <EmptyState
                                v-else
                                class="border-0 bg-transparent py-8"
                                title="Sem resultados agregados"
                                description="Quando houver campanhas concluídas com resultado global, o gráfico aparece aqui."
                            />
                        </div>
                        <aside
                            v-if="riskDonutTotal > 0"
                            class="dashboard-chart-aside gap-4 lg:w-60 lg:border-0 lg:bg-transparent lg:p-0 lg:pl-2"
                        >
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Legenda</p>
                            <ul class="space-y-3">
                                <li v-for="row in riskLegend" :key="row.key" class="flex items-center justify-between gap-3 text-sm">
                                    <span class="flex items-center gap-2 text-slate-700">
                                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="row.color" />
                                        {{ row.label }}
                                    </span>
                                    <span class="tabular-nums font-semibold text-slate-900">{{ row.count }}</span>
                                </li>
                            </ul>
                            <div class="mt-2 border-t border-slate-200 pt-4">
                                <p class="text-xs text-slate-500">Total de empresas (última campanha)</p>
                                <p class="font-serif text-3xl font-bold text-talents-800">{{ riskDonutTotal }}</p>
                            </div>
                        </aside>
                    </div>
                    <div class="mt-8 border-t border-slate-100 pt-6">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Média por segmento (0–100)</p>
                        <div class="mt-3 space-y-3">
                            <ProgressBar
                                v-for="row in riskBySegment"
                                :key="row.segment"
                                :label="row.segment"
                                :value="(100 * (Number(row.avg_score) || 0)) / maxSegmentScore"
                                :display-value="Number(row.avg_score).toFixed(1)"
                                bar-class="bg-talents-600"
                            />
                            <EmptyState
                                v-if="!riskBySegment?.length"
                                class="border-0 bg-transparent py-6"
                                title="Sem dados por segmento"
                                description="Defina o segmento nas empresas para comparar."
                            />
                        </div>
                    </div>
                </div>

                <div class="dashboard-panel">
                    <SectionHeader title="Empresas com saúde crítica" subtitle="Última campanha — nível vermelho" />
                    <ul v-if="criticalCompanies?.length" class="mt-4 divide-y divide-slate-100">
                        <li
                            v-for="c in criticalCompanies"
                            :key="c.id"
                            class="flex flex-wrap items-center justify-between gap-3 rounded-xl py-3 transition first:pt-0 hover:bg-talents-50/70 sm:px-2"
                        >
                            <div class="min-w-0">
                                <Link
                                    :href="route('admin.companies.show', c.id)"
                                    class="font-medium text-talents-900 hover:text-talents-700 hover:underline"
                                >
                                    {{ c.name }}
                                </Link>
                                <p v-if="c.segment" class="text-xs text-slate-500">{{ c.segment }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs tabular-nums text-slate-600">Média {{ Number(c.average_score).toFixed(1) }}</span>
                                <HealthBadge :risk-level="c.risk_level" />
                            </div>
                        </li>
                    </ul>
                    <EmptyState
                        v-else
                        class="mt-2 border-0 bg-transparent"
                        title="Nenhuma empresa crítica"
                        description="Ótimo — nenhuma última campanha com nível vermelho no momento."
                    />
                </div>
            </div>

            <div class="space-y-6">
                <div class="dashboard-panel-compact">
                    <SectionHeader title="Próximos 7 dias" subtitle="Calendário estratégico" />
                    <ul v-if="upcomingCalendar?.length" class="mt-2 space-y-3 text-sm">
                        <li v-for="item in upcomingCalendar" :key="item.id" class="border-b border-slate-100 pb-3 last:border-0 last:pb-0">
                            <p class="text-xs font-medium text-slate-500">
                                {{ formatShortDate(item.occurs_on) }}
                                <span v-if="item.company" class="text-slate-400"> · {{ item.company.name }}</span>
                            </p>
                            <p class="mt-0.5 font-medium text-slate-900">{{ item.title }}</p>
                            <p class="mt-0.5 text-xs text-talents-700">
                                {{ dashboardCalendar?.kindLabels?.[item.kind] ?? item.kind }}
                            </p>
                        </li>
                    </ul>
                    <EmptyState
                        v-else
                        class="mt-2 border-0 bg-transparent py-6"
                        title="Nada agendado"
                        description="Sem itens de calendário nos próximos 7 dias."
                        :cta-href="route('admin.strategic-calendar.index')"
                        cta-label="Gerir calendário"
                    />
                </div>

                <div class="dashboard-panel-compact">
                    <SectionHeader title="Leads recentes" subtitle="E-mail ainda não enviado (follow-up)">
                        <template #action>
                            <Link :href="route('admin.landing-interest.index')" class="text-xs font-semibold text-talents-700 hover:underline">
                                Ver todos
                            </Link>
                        </template>
                    </SectionHeader>
                    <ul v-if="recentLeads?.length" class="mt-3 space-y-3 text-sm">
                        <li v-for="lead in recentLeads" :key="lead.id" class="dashboard-inset-row">
                            <p class="font-medium text-slate-900">{{ lead.name }}</p>
                            <p class="text-xs text-slate-600">{{ lead.email }}</p>
                            <p v-if="lead.company" class="mt-1 text-xs text-slate-500">{{ lead.company }}</p>
                        </li>
                    </ul>
                    <EmptyState v-else class="mt-2 border-0 bg-transparent py-6" title="Sem leads pendentes" />
                </div>

                <div class="dashboard-panel-compact">
                    <SectionHeader title="Renovações (30 dias)" subtitle="Assinaturas ativas a terminar" />
                    <ul v-if="subscriptionsDueSoon?.length" class="mt-3 space-y-2 text-sm">
                        <li v-for="sub in subscriptionsDueSoon" :key="sub.id">
                            <Link
                                :href="route('admin.companies.show', sub.company_id)"
                                class="font-medium text-talents-800 hover:underline"
                            >
                                {{ sub.company_name }}
                            </Link>
                            <p class="text-xs text-slate-500">Até {{ formatShortDate(sub.ends_at) }}</p>
                        </li>
                    </ul>
                    <EmptyState v-else class="mt-2 border-0 bg-transparent py-6" title="Sem renovações próximas" />
                </div>

                <div class="dashboard-panel-compact">
                    <SectionHeader title="Denúncias por empresa" subtitle="Top 5 — pendentes" />
                    <ul v-if="pendingComplaints?.length" class="mt-3 space-y-2 text-sm">
                        <li v-for="row in pendingComplaints" :key="row.company_id" class="flex justify-between gap-2">
                            <Link :href="route('admin.companies.show', row.company_id)" class="truncate font-medium text-talents-800 hover:underline">
                                {{ row.company_name }}
                            </Link>
                            <span class="shrink-0 tabular-nums text-slate-600">{{ row.count }}</span>
                        </li>
                    </ul>
                    <EmptyState v-else class="mt-2 border-0 bg-transparent py-6" title="Sem denúncias pendentes" />
                </div>
            </div>
        </div>

        <div v-if="dashboardCalendar" class="mt-10">
            <StrategicCalendarWidget
                :items="dashboardCalendar.items"
                :year="dashboardCalendar.year"
                :month="dashboardCalendar.month"
                :kind-labels="dashboardCalendar.kindLabels"
                title="Calendário estratégico"
                subtitle="Eventos e ritos do mês — visão completa"
                :full-page-href="route('admin.strategic-calendar.index')"
                full-page-label="Gerenciar itens"
                dashboard-route="admin.dashboard"
            />
        </div>
    </AdminLayout>
</template>
