<script setup>
import ApexChart from '@/Components/Charts/ApexChart.vue';
import EmptyState from '@/Components/Dashboard/EmptyState.vue';
import HealthBadge from '@/Components/Dashboard/HealthBadge.vue';
import ProgressBar from '@/Components/Dashboard/ProgressBar.vue';
import SectionHeader from '@/Components/Dashboard/SectionHeader.vue';
import StatCard from '@/Components/Dashboard/StatCard.vue';
import Modal from '@/Components/Modal.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useDashboardGreeting } from '@/composables/useDashboardGreeting';
import { daysFromToday, formatDateLong, formatDateShort } from '@/utils/dateOnly';
import RhidPortfolioSection from '@/Components/Admin/RhidPortfolioSection.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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
    calendarKindLabels: { type: Object, default: () => ({}) },
});

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
    labels: ['Situação favorável', 'Risco intermediário', 'Risco elevado'],
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
    return Math.max(5, ...rows.map((r) => Number(r.avg_score) || 0));
});

const formatShortDate = (iso) => formatDateShort(iso);

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

/** Diferença em dias entre hoje e a data `occurs_on` do evento (0 = hoje). */
const calendarEventDaysFromToday = computed(() => daysFromToday(nextCalendarEvent.value?.occurs_on));

/** Chip discreto que situa o evento no tempo sem repetir a data inteira. */
const calendarEventChip = computed(() => {
    const n = calendarEventDaysFromToday.value;
    if (n === null || n === undefined) return null;
    if (n === 0) return { label: 'Hoje', tone: 'today' };
    if (n === 1) return { label: 'Amanhã', tone: 'soon' };
    if (n > 1 && n <= 7) return { label: `Em ${n} dias`, tone: 'soon' };
    if (n < 0) return { label: 'No passado', tone: 'past' };
    return { label: `Em ${n} dias`, tone: 'far' };
});

const calendarKindLabel = (kind) => {
    const k = typeof kind === 'object' && kind?.value !== undefined ? kind.value : kind;
    return props.calendarKindLabels?.[k] ?? k ?? '—';
};

const formatEventLong = (item) => formatDateLong(item?.occurs_on);

/**
 * Extrai "Horário: HH:MM – HH:MM" da descrição do item.
 * Aceita os separadores "–", "-" e "—".
 */
function extractEventTimeRange(description) {
    if (!description) return null;
    const re = /Hor[áa]rio:\s*(\d{1,2}:\d{2})\s*[–\-—]\s*(\d{1,2}:\d{2})/iu;
    const m = String(description).match(re);
    if (!m) return null;
    return { start: m[1].padStart(5, '0'), end: m[2].padStart(5, '0') };
}

const calendarEventTime = computed(() => extractEventTimeRange(nextCalendarEvent.value?.description));

const riskLegend = computed(() => {
    const d = props.riskDistribution || {};
    return [
        { key: 'green', label: 'Situação favorável', count: d.green || 0, color: 'bg-emerald-500' },
        { key: 'yellow', label: 'Risco intermediário', count: d.yellow || 0, color: 'bg-amber-500' },
        { key: 'red', label: 'Risco elevado', count: d.red || 0, color: 'bg-rose-500' },
    ];
});

const criticalCount = computed(() => props.criticalCompanies?.length ?? 0);

const showCalendarModal = ref(false);
const showAlertsModal = ref(false);
const showLeadsModal = ref(false);
const showLeadDetailModal = ref(false);
const selectedLead = ref(null);

const formatLeadDate = (iso) => formatDateLong(iso);

const openLeadDetail = (lead) => {
    selectedLead.value = lead;
    showLeadDetailModal.value = true;
};

const closeLeadDetail = () => {
    showLeadDetailModal.value = false;
};

const leadInitials = computed(() => {
    const full = selectedLead.value?.name || '';
    const parts = full.split(/\s+/).filter(Boolean);
    if (parts.length >= 2) {
        return `${parts[0][0] ?? ''}${parts[1][0] ?? ''}`.toUpperCase();
    }
    return (parts[0]?.slice(0, 2) || '?').toUpperCase();
});

/** Telefone limpo (apenas dígitos e "+") para uso em links wa.me/tel: */
const leadPhoneDigits = computed(() => {
    const raw = selectedLead.value?.phone || '';
    return raw.replace(/[^0-9+]/g, '');
});

const leadWhatsappUrl = computed(() => {
    const digits = leadPhoneDigits.value.replace(/^\+/, '');
    return digits ? `https://wa.me/${digits}` : '#';
});
</script>

<template>
    <Head title="Painel Admin" />

    <AdminLayout>
        <template #topbar>
            <div
                class="hidden shrink-0 items-center justify-between gap-4 border-b border-slate-200/40 bg-white/75 px-6 py-3 backdrop-blur-md sm:flex lg:rounded-t-shell lg:px-8"
            >
                <h2 class="truncate text-base font-semibold tracking-tight text-slate-900 sm:text-lg">
                    Visão geral Talents
                </h2>
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

        <!-- Hero + status (estilo cartões principais) -->
        <div class="mb-8 grid gap-4 lg:grid-cols-4">
            <div
                role="button"
                tabindex="0"
                class="dashboard-hero group cursor-pointer text-left transition hover:shadow-xl hover:ring-2 hover:ring-talents-300/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-300/60 lg:col-span-2"
                aria-haspopup="dialog"
                @click="showCalendarModal = true"
                @keydown.enter.prevent="showCalendarModal = true"
                @keydown.space.prevent="showCalendarModal = true"
            >
                <div class="dashboard-hero-blob -right-24 -top-24 h-56 w-56 opacity-70" />
                <div class="dashboard-hero-blob-accent -bottom-24 left-1/3 h-48 w-48 opacity-60" />

                <div class="relative flex flex-col gap-7">
                    <!-- Saudação -->
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex min-w-0 items-center gap-3.5">
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-white/12 text-sm font-semibold tracking-wide text-white ring-1 ring-white/25"
                                aria-hidden="true"
                            >
                                {{ userInitials }}
                            </div>
                            <div class="min-w-0">
                                <p class="text-[11px] font-medium uppercase tracking-[0.18em] text-white/55">Bem-vindo</p>
                                <p class="mt-0.5 truncate text-xl font-semibold tracking-tight text-white sm:text-2xl">
                                    {{ greeting.prefix }},
                                    <span class="font-bold">{{ greeting.first }}</span>
                                </p>
                            </div>
                        </div>
                        <Link
                            :href="route('admin.strategic-calendar.index')"
                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-white/25 bg-white/10 text-white/90 transition hover:bg-white/20 hover:text-white focus:outline-none focus:ring-2 focus:ring-white/60"
                            aria-label="Abrir calendário estratégico"
                            @click.stop
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </Link>
                    </div>

                    <div class="h-px w-full bg-white/15" />

                    <!-- Próximo evento -->
                    <div v-if="nextCalendarEvent" class="min-w-0">
                        <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1.5">
                            <span class="text-[11px] font-medium uppercase tracking-[0.18em] text-white/55">Próximo evento</span>
                            <span
                                v-if="calendarEventChip"
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold tracking-wide"
                                :class="{
                                    'bg-white text-talents-700': calendarEventChip.tone === 'today',
                                    'bg-white/15 text-white ring-1 ring-white/25':
                                        calendarEventChip.tone === 'soon' || calendarEventChip.tone === 'far',
                                    'bg-rose-500/20 text-rose-100 ring-1 ring-rose-300/40': calendarEventChip.tone === 'past',
                                }"
                            >
                                {{ calendarEventChip.label }}
                            </span>
                        </div>

                        <h3 class="mt-2 font-serif text-2xl font-bold leading-tight text-white sm:text-3xl">
                            {{ nextCalendarEvent.title }}
                        </h3>

                        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-sm text-white/85 sm:text-base">
                            <span class="inline-flex items-center gap-2">
                                <svg class="h-4 w-4 shrink-0 text-white/65" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M6.75 3v2.25M17.25 3v2.25M3.75 8.25h16.5M5.25 5.25h13.5A1.5 1.5 0 0120.25 6.75v12.75A1.5 1.5 0 0118.75 21H5.25a1.5 1.5 0 01-1.5-1.5V6.75a1.5 1.5 0 011.5-1.5z"
                                    />
                                </svg>
                                {{ formatEventLong(nextCalendarEvent) }}
                            </span>
                            <span v-if="calendarEventTime" class="inline-flex items-center gap-2 text-white/90">
                                <svg class="h-4 w-4 shrink-0 text-white/65" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2" />
                                    <circle cx="12" cy="12" r="9" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <span class="tabular-nums">{{ calendarEventTime.start }}</span>
                                <span class="text-white/55">–</span>
                                <span class="tabular-nums">{{ calendarEventTime.end }}</span>
                            </span>
                        </div>

                        <p class="mt-1.5 text-xs text-white/65">
                            {{ calendarKindLabel(nextCalendarEvent.kind) }}
                            <span v-if="nextCalendarEvent.company"> · {{ nextCalendarEvent.company.name }}</span>
                        </p>
                    </div>

                    <div v-else class="min-w-0">
                        <p class="text-[11px] font-medium uppercase tracking-[0.18em] text-white/55">Próximo evento</p>
                        <h3 class="mt-2 font-serif text-2xl font-semibold leading-tight text-white sm:text-3xl">
                            Sem eventos nos próximos 7 dias
                        </h3>
                        <p class="mt-2 max-w-md text-sm text-white/75">
                            Quando existir um item no calendário estratégico desta semana, a data aparece aqui — em destaque, com o dia agendado.
                        </p>
                    </div>
                </div>
            </div>

            <div
                role="button"
                tabindex="0"
                class="dashboard-accent-dark group cursor-pointer text-left text-white transition hover:shadow-xl hover:ring-2 hover:ring-talents-300/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-300/60"
                aria-haspopup="dialog"
                @click="showAlertsModal = true"
                @keydown.enter.prevent="showAlertsModal = true"
                @keydown.space.prevent="showAlertsModal = true"
            >
                <div class="dashboard-hero-blob right-0 top-0 h-32 w-32 translate-x-1/3 -translate-y-1/3 bg-talents-300/25" />
                <div class="relative">
                    <p class="text-[11px] font-medium uppercase tracking-[0.18em] text-talents-100/75">Alertas NR-1</p>
                    <h3 class="mt-1.5 text-base font-semibold text-white">Resumo rápido</h3>
                    <div class="mt-5 space-y-2.5">
                        <div class="flex items-center justify-between gap-2 rounded-xl px-3 py-2.5 ring-1 ring-white/10">
                            <span class="text-sm text-talents-50/80">Empresas críticas</span>
                            <span class="text-xl font-bold tabular-nums text-rose-300">{{ criticalCount }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-2 rounded-xl px-3 py-2.5 ring-1 ring-white/10">
                            <span class="text-sm text-talents-50/80">Denúncias abertas</span>
                            <span class="text-xl font-bold tabular-nums text-amber-200">{{ stats.pending_complaints_total }}</span>
                        </div>
                    </div>
                </div>
                <Link
                    :href="route('admin.companies.index')"
                    class="relative mt-6 inline-flex w-full items-center justify-center rounded-xl border border-white/15 bg-white/5 py-2.5 text-sm font-semibold text-white/90 transition hover:bg-white/15 hover:text-white"
                    @click.stop
                >
                    Ver empresas
                </Link>
            </div>

            <div
                role="button"
                tabindex="0"
                class="dashboard-accent-dark group cursor-pointer text-left text-white transition hover:shadow-xl hover:ring-2 hover:ring-talents-300/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-300/60"
                aria-haspopup="dialog"
                @click="showLeadsModal = true"
                @keydown.enter.prevent="showLeadsModal = true"
                @keydown.space.prevent="showLeadsModal = true"
            >
                <div class="dashboard-hero-blob -right-10 -top-10 h-32 w-32 bg-talents-300/30" />
                <div class="relative flex h-full flex-col">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[11px] font-medium uppercase tracking-[0.18em] text-talents-100/75">Leads recentes</p>
                            <h3 class="mt-1.5 text-base font-semibold text-white">Interessados · follow-up</h3>
                        </div>
                        <Link
                            :href="route('admin.landing-interest.index')"
                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-white/20 bg-white/10 text-white/90 transition hover:bg-white/20 hover:text-white focus:outline-none focus:ring-2 focus:ring-white/60"
                            aria-label="Ver todos os leads"
                            @click.stop
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </Link>
                    </div>

                    <ul v-if="recentLeads?.length" class="mt-5 space-y-2 text-sm">
                        <li
                            v-for="lead in recentLeads.slice(0, 4)"
                            :key="lead.id"
                            class="cursor-pointer rounded-xl px-3 py-2.5 ring-1 ring-white/10 transition hover:bg-white/10 hover:ring-white/20"
                            role="button"
                            tabindex="0"
                            @click.stop="openLeadDetail(lead)"
                            @keydown.enter.prevent.stop="openLeadDetail(lead)"
                            @keydown.space.prevent.stop="openLeadDetail(lead)"
                        >
                            <p class="truncate font-medium leading-snug text-white">{{ lead.name }}</p>
                            <p class="mt-0.5 truncate text-xs text-talents-100/80">{{ lead.email }}</p>
                        </li>
                        <li
                            v-if="recentLeads.length > 4"
                            class="rounded-xl px-3 py-2 text-center text-xs font-medium text-talents-100/75"
                        >
                            + {{ recentLeads.length - 4 }} leads · clique para ver
                        </li>
                    </ul>
                    <div v-else class="mt-5 rounded-xl px-3 py-6 text-center text-sm text-talents-100/65 ring-1 ring-white/10">
                        Sem leads pendentes
                    </div>
                </div>
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
                        title="Risco psicossocial"
                        subtitle="Distribuição na última campanha de cada empresa (resultado global)"
                    />
                    <div class="mt-6 flex flex-col gap-8 lg:flex-row lg:items-start">
                        <div class="flex min-h-[220px] min-w-0 flex-1 justify-center">
                            <ApexChart
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
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Média de risco por segmento (1–5)</p>
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
                    <SectionHeader title="Empresas com risco elevado" subtitle="Última campanha — nível vermelho" />
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

        <RhidPortfolioSection />

        <Modal :show="showCalendarModal" max-width="2xl" @close="showCalendarModal = false">
            <div class="dashboard-accent-dark !rounded-lg text-white">
                <div class="dashboard-hero-blob -right-16 -top-16 h-40 w-40 bg-talents-300/25" />
                <div class="relative">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-[11px] font-medium uppercase tracking-[0.18em] text-talents-100/75">
                                Calendário estratégico
                            </p>
                            <h3 class="mt-1.5 font-serif text-2xl font-bold text-white">Próximos 7 dias</h3>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/20 bg-white/10 text-white/90 transition hover:bg-white/20 hover:text-white focus:outline-none focus:ring-2 focus:ring-white/60"
                            aria-label="Fechar"
                            @click="showCalendarModal = false"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <ul v-if="upcomingCalendar?.length" class="mt-6 space-y-2">
                        <li
                            v-for="event in upcomingCalendar"
                            :key="event.id"
                            class="rounded-xl px-4 py-3 ring-1 ring-white/10 transition hover:bg-white/5"
                        >
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <p class="font-semibold leading-snug text-white">{{ event.title }}</p>
                                <span class="text-xs tabular-nums text-talents-100/75">{{ formatEventLong(event) }}</span>
                            </div>
                            <p class="mt-1 text-xs text-talents-100/70">
                                {{ calendarKindLabel(event.kind) }}
                                <span v-if="event.company"> · {{ event.company.name }}</span>
                            </p>
                            <p v-if="event.description" class="mt-2 text-sm text-talents-50/90">{{ event.description }}</p>
                        </li>
                    </ul>
                    <div v-else class="mt-6 rounded-xl px-4 py-8 text-center text-sm text-talents-100/65 ring-1 ring-white/10">
                        Sem eventos nos próximos 7 dias
                    </div>

                    <Link
                        :href="route('admin.strategic-calendar.index')"
                        class="mt-6 inline-flex w-full items-center justify-center rounded-xl border border-white/15 bg-white/5 py-2.5 text-sm font-semibold text-white/90 transition hover:bg-white/15 hover:text-white"
                    >
                        Abrir calendário completo
                    </Link>
                </div>
            </div>
        </Modal>

        <Modal :show="showAlertsModal" max-width="2xl" @close="showAlertsModal = false">
            <div class="dashboard-accent-dark !rounded-lg text-white">
                <div class="dashboard-hero-blob right-0 top-0 h-32 w-32 translate-x-1/3 -translate-y-1/3 bg-talents-300/25" />
                <div class="relative">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-[11px] font-medium uppercase tracking-[0.18em] text-talents-100/75">Alertas NR-1</p>
                            <h3 class="mt-1.5 font-serif text-2xl font-bold text-white">Resumo de criticidade</h3>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/20 bg-white/10 text-white/90 transition hover:bg-white/20 hover:text-white focus:outline-none focus:ring-2 focus:ring-white/60"
                            aria-label="Fechar"
                            @click="showAlertsModal = false"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-xl px-4 py-3 ring-1 ring-white/10">
                            <p class="text-xs uppercase tracking-wide text-talents-100/70">Empresas críticas</p>
                            <p class="mt-1 text-2xl font-bold tabular-nums text-rose-300">{{ criticalCount }}</p>
                        </div>
                        <div class="rounded-xl px-4 py-3 ring-1 ring-white/10">
                            <p class="text-xs uppercase tracking-wide text-talents-100/70">Denúncias pendentes</p>
                            <p class="mt-1 text-2xl font-bold tabular-nums text-amber-200">{{ stats.pending_complaints_total }}</p>
                        </div>
                    </div>

                    <div class="mt-6">
                        <p class="text-xs font-semibold uppercase tracking-wide text-talents-100/75">Empresas com risco crítico</p>
                        <ul v-if="criticalCompanies?.length" class="mt-2 space-y-2">
                            <li
                                v-for="c in criticalCompanies"
                                :key="c.id"
                                class="flex flex-wrap items-center justify-between gap-3 rounded-xl px-4 py-2.5 ring-1 ring-white/10"
                            >
                                <div class="min-w-0">
                                    <Link
                                        :href="route('admin.companies.show', c.id)"
                                        class="font-medium text-white hover:underline"
                                    >
                                        {{ c.name }}
                                    </Link>
                                    <p v-if="c.segment" class="text-xs text-talents-100/65">{{ c.segment }}</p>
                                </div>
                                <span class="inline-flex items-center gap-2 text-xs tabular-nums">
                                    <span class="text-talents-100/75">Média {{ Number(c.average_score).toFixed(1) }}</span>
                                    <span class="inline-flex h-2 w-2 rounded-full bg-rose-400" aria-hidden="true" />
                                </span>
                            </li>
                        </ul>
                        <p v-else class="mt-2 rounded-xl px-4 py-4 text-sm text-talents-100/65 ring-1 ring-white/10">
                            Nenhuma empresa crítica no momento.
                        </p>
                    </div>

                    <div class="mt-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-talents-100/75">Denúncias por empresa (top 5)</p>
                        <ul v-if="pendingComplaints?.length" class="mt-2 space-y-2">
                            <li
                                v-for="row in pendingComplaints"
                                :key="row.company_id"
                                class="flex items-center justify-between gap-3 rounded-xl px-4 py-2.5 ring-1 ring-white/10"
                            >
                                <Link
                                    :href="route('admin.companies.show', row.company_id)"
                                    class="truncate font-medium text-white hover:underline"
                                >
                                    {{ row.company_name }}
                                </Link>
                                <span class="shrink-0 text-sm font-semibold tabular-nums text-amber-200">{{ row.count }}</span>
                            </li>
                        </ul>
                        <p v-else class="mt-2 rounded-xl px-4 py-4 text-sm text-talents-100/65 ring-1 ring-white/10">
                            Sem denúncias pendentes.
                        </p>
                    </div>

                    <Link
                        :href="route('admin.companies.index')"
                        class="mt-6 inline-flex w-full items-center justify-center rounded-xl border border-white/15 bg-white/5 py-2.5 text-sm font-semibold text-white/90 transition hover:bg-white/15 hover:text-white"
                    >
                        Ver todas as empresas
                    </Link>
                </div>
            </div>
        </Modal>

        <Modal :show="showLeadsModal" max-width="2xl" @close="showLeadsModal = false">
            <div class="dashboard-accent-dark !rounded-lg text-white">
                <div class="dashboard-hero-blob -right-10 -top-10 h-32 w-32 bg-talents-300/30" />
                <div class="relative">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-[11px] font-medium uppercase tracking-[0.18em] text-talents-100/75">Leads recentes</p>
                            <h3 class="mt-1.5 font-serif text-2xl font-bold text-white">Interessados · follow-up</h3>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/20 bg-white/10 text-white/90 transition hover:bg-white/20 hover:text-white focus:outline-none focus:ring-2 focus:ring-white/60"
                            aria-label="Fechar"
                            @click="showLeadsModal = false"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <ul v-if="recentLeads?.length" class="mt-6 max-h-[60vh] space-y-2 overflow-y-auto pr-1">
                        <li
                            v-for="lead in recentLeads"
                            :key="lead.id"
                            class="group cursor-pointer rounded-xl px-4 py-3 ring-1 ring-white/10 transition hover:bg-white/10 hover:ring-white/20"
                            role="button"
                            tabindex="0"
                            @click="openLeadDetail(lead)"
                            @keydown.enter.prevent="openLeadDetail(lead)"
                            @keydown.space.prevent="openLeadDetail(lead)"
                        >
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <p class="font-semibold leading-snug text-white">{{ lead.name }}</p>
                                <span v-if="lead.created_at" class="text-xs tabular-nums text-talents-100/70">
                                    {{ formatLeadDate(lead.created_at) }}
                                </span>
                            </div>
                            <p class="mt-0.5 truncate text-sm text-talents-50/85">{{ lead.email }}</p>
                            <p v-if="lead.company" class="mt-1 truncate text-xs text-talents-100/70">{{ lead.company }}</p>
                            <p class="mt-2 inline-flex items-center gap-1 text-[11px] font-medium uppercase tracking-wide text-talents-100/60 transition group-hover:text-white">
                                Ver detalhes
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </p>
                        </li>
                    </ul>
                    <div v-else class="mt-6 rounded-xl px-4 py-8 text-center text-sm text-talents-100/65 ring-1 ring-white/10">
                        Sem leads pendentes
                    </div>

                    <Link
                        :href="route('admin.landing-interest.index')"
                        class="mt-6 inline-flex w-full items-center justify-center rounded-xl border border-white/15 bg-white/5 py-2.5 text-sm font-semibold text-white/90 transition hover:bg-white/15 hover:text-white"
                    >
                        Abrir gestão completa de leads
                    </Link>
                </div>
            </div>
        </Modal>

        <Modal :show="showLeadDetailModal" max-width="lg" @close="closeLeadDetail">
            <div v-if="selectedLead" class="dashboard-accent-dark !rounded-lg text-white">
                <div class="dashboard-hero-blob -right-10 -top-10 h-32 w-32 bg-talents-300/30" />
                <div class="relative">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex min-w-0 items-start gap-3">
                            <div
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white/10 text-base font-semibold tracking-wide text-white ring-1 ring-white/25"
                                aria-hidden="true"
                            >
                                {{ leadInitials }}
                            </div>
                            <div class="min-w-0">
                                <p class="text-[11px] font-medium uppercase tracking-[0.18em] text-talents-100/75">Lead</p>
                                <h3 class="mt-0.5 truncate font-serif text-2xl font-bold leading-tight text-white">
                                    {{ selectedLead.name }}
                                </h3>
                                <p v-if="selectedLead.created_at" class="mt-1 text-xs text-talents-100/70">
                                    Enviado em {{ formatLeadDate(selectedLead.created_at) }}
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-white/20 bg-white/10 text-white/90 transition hover:bg-white/20 hover:text-white focus:outline-none focus:ring-2 focus:ring-white/60"
                            aria-label="Fechar"
                            @click="closeLeadDetail"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <dl class="mt-6 space-y-2.5">
                        <div class="rounded-xl px-4 py-3 ring-1 ring-white/10">
                            <dt class="text-[11px] font-medium uppercase tracking-wide text-talents-100/70">E-mail</dt>
                            <dd class="mt-0.5 break-all text-sm font-medium text-white">
                                <a :href="`mailto:${selectedLead.email}`" class="hover:underline">{{ selectedLead.email }}</a>
                            </dd>
                        </div>
                        <div v-if="selectedLead.phone" class="rounded-xl px-4 py-3 ring-1 ring-white/10">
                            <dt class="text-[11px] font-medium uppercase tracking-wide text-talents-100/70">Telefone</dt>
                            <dd class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm font-medium text-white">
                                <a :href="`tel:${leadPhoneDigits}`" class="hover:underline">{{ selectedLead.phone }}</a>
                                <a
                                    v-if="leadPhoneDigits"
                                    :href="leadWhatsappUrl"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center gap-1 rounded-full bg-emerald-500/20 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-200 ring-1 ring-emerald-300/30 hover:bg-emerald-500/30"
                                >
                                    WhatsApp
                                </a>
                            </dd>
                        </div>
                        <div v-if="selectedLead.company" class="rounded-xl px-4 py-3 ring-1 ring-white/10">
                            <dt class="text-[11px] font-medium uppercase tracking-wide text-talents-100/70">Empresa</dt>
                            <dd class="mt-0.5 text-sm font-medium text-white">{{ selectedLead.company }}</dd>
                        </div>
                        <div v-if="selectedLead.message" class="rounded-xl px-4 py-3 ring-1 ring-white/10">
                            <dt class="text-[11px] font-medium uppercase tracking-wide text-talents-100/70">Mensagem</dt>
                            <dd class="mt-1 whitespace-pre-line text-sm leading-relaxed text-talents-50/95">
                                {{ selectedLead.message }}
                            </dd>
                        </div>
                        <div class="rounded-xl px-4 py-3 ring-1 ring-white/10">
                            <dt class="text-[11px] font-medium uppercase tracking-wide text-talents-100/70">Status</dt>
                            <dd class="mt-1 inline-flex items-center gap-2 text-xs font-semibold">
                                <span
                                    v-if="selectedLead.mail_sent_at"
                                    class="rounded-full bg-emerald-500/20 px-2.5 py-0.5 text-emerald-200 ring-1 ring-emerald-300/30"
                                >
                                    E-mail enviado
                                </span>
                                <span
                                    v-else
                                    class="rounded-full bg-amber-500/20 px-2.5 py-0.5 text-amber-100 ring-1 ring-amber-300/30"
                                >
                                    Pendente · follow-up
                                </span>
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-6 flex flex-wrap gap-2">
                        <a
                            :href="`mailto:${selectedLead.email}`"
                            class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-talents-900 shadow-sm transition hover:bg-talents-50"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l9 6 9-6M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            Responder por e-mail
                        </a>
                        <Link
                            :href="route('admin.landing-interest.index')"
                            class="inline-flex flex-1 items-center justify-center rounded-xl border border-white/20 bg-white/5 px-4 py-2.5 text-sm font-semibold text-white/90 transition hover:bg-white/15 hover:text-white"
                        >
                            Ver todos os leads
                        </Link>
                    </div>
                </div>
            </div>
        </Modal>
    </AdminLayout>
</template>
