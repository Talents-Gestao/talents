<script setup>
import EmptyState from '@/Components/Dashboard/EmptyState.vue';
import HealthBadge from '@/Components/Dashboard/HealthBadge.vue';
import ProgressBar from '@/Components/Dashboard/ProgressBar.vue';
import SectionHeader from '@/Components/Dashboard/SectionHeader.vue';
import StatCard from '@/Components/Dashboard/StatCard.vue';
import StrategicCalendarWidget from '@/Components/StrategicCalendarWidget.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { useDashboardGreeting } from '@/composables/useDashboardGreeting';
import { usePermissions } from '@/composables/usePermissions';
import { formatDateNumeric, formatRelativeDate } from '@/utils/dateOnly';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const { can } = usePermissions();
const greeting = useDashboardGreeting();

const copied = ref(false);

const copyDenuncia = async (url) => {
    if (!url || !navigator.clipboard) return;
    await navigator.clipboard.writeText(url);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
};

const props = defineProps({
    activeSurveys: Number,
    lastSurvey: Object,
    overallRisk: Object,
    lastCampaign: {
        type: Object,
        default: () => ({ completion_rate: null, section_results: [] }),
    },
    pendingComplaintsCount: { type: Number, default: 0 },
    openActionPlanCount: { type: Number, default: 0 },
    openActionPlanItems: { type: Array, default: () => [] },
    pendingTasks: { type: Array, default: () => [] },
    upcomingCalendar: { type: Array, default: null },
    calendarKindLabels: { type: Object, default: () => ({}) },
    actionPlanHref: { type: String, default: null },
    complaintsPublicUrl: { type: String, default: null },
    dashboardCalendar: { type: Object, default: null },
});

const companyName = computed(() => page.props.company?.name ?? '');

const todayLabel = computed(() =>
    new Date().toLocaleDateString('pt-BR', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }),
);

const formatRelative = (iso) => formatRelativeDate(iso);
const formatDateTitle = (iso) => {
    if (!iso) return undefined;
    const relative = formatRelativeDate(iso);
    const absolute = formatDateNumeric(iso);
    if (!absolute || relative === absolute) return absolute;
    return `${relative} (${absolute})`;
};

const kindLabel = (kind) => {
    const k = typeof kind === 'object' && kind?.value !== undefined ? kind.value : kind;
    return props.calendarKindLabels?.[k] ?? k;
};

const userInitials = computed(() => {
    const full = greeting.value.full || '';
    const parts = full.split(/\s+/).filter(Boolean);
    if (parts.length >= 2) {
        return `${parts[0][0] ?? ''}${parts[1][0] ?? ''}`.toUpperCase();
    }
    return (parts[0]?.slice(0, 2) || 'ME').toUpperCase();
});

const nextCalendarItem = computed(() => {
    const u = props.upcomingCalendar;
    if (!u?.length) return null;
    return u[0];
});

const attentionTotal = computed(() => {
    let n = 0;
    if (can('denuncias', 'view')) n += props.pendingComplaintsCount || 0;
    if (can('tarefas', 'view')) n += props.pendingTasks?.length || 0;
    return n;
});
</script>

<template>
    <Head title="Painel" />

    <ClientLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex min-w-0 items-center gap-3">
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-talents-600 to-violet-700 text-sm font-bold text-white shadow-md"
                        aria-hidden="true"
                    >
                        {{ userInitials }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs capitalize text-slate-500">{{ todayLabel }}</p>
                        <p class="text-sm text-slate-600">
                            {{ greeting.prefix }}, <span class="font-semibold text-slate-900">{{ greeting.first }}</span>
                        </p>
                        <h2 class="mt-0.5 truncate text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">Painel NR-1</h2>
                        <p v-if="companyName" class="truncate text-sm text-slate-600">{{ companyName }}</p>
                    </div>
                </div>
                <Link
                    v-if="attentionTotal > 0"
                    :href="can('denuncias', 'view') ? route('client.complaints.index') : route('client.tarefas.index')"
                    class="dashboard-header-cta"
                >
                    <span class="dashboard-header-cta-badge">
                        {{ attentionTotal }}
                    </span>
                    Pendências
                </Link>
            </div>
        </template>

        <template #aside>
            <div class="space-y-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Atalhos</p>
                <div class="surface-glass space-y-3 p-4 text-sm text-slate-700">
                    <Link
                        v-if="can('pesquisas', 'view')"
                        :href="route('client.surveys.index')"
                        class="block font-medium text-talents-800 hover:underline"
                    >
                        Pesquisas NR1
                    </Link>
                    <Link
                        v-if="can('planos_acao', 'view') && actionPlanHref"
                        :href="actionPlanHref"
                        class="block font-medium text-talents-800 hover:underline"
                    >
                        Plano de ação
                    </Link>
                    <Link
                        v-if="can('denuncias', 'view')"
                        :href="route('client.complaints.index')"
                        class="block font-medium text-talents-800 hover:underline"
                    >
                        Denúncias
                    </Link>
                    <Link
                        v-if="can('tarefas', 'view')"
                        :href="route('client.tarefas.index')"
                        class="block font-medium text-talents-800 hover:underline"
                    >
                        Tarefas
                    </Link>
                    <Link
                        v-if="can('calendario_estrategico', 'view')"
                        :href="route('client.strategic-calendar.index')"
                        class="block font-medium text-talents-800 hover:underline"
                    >
                        Calendário estratégico
                    </Link>
                    <Link :href="route('profile.edit')" class="block font-medium text-talents-800 hover:underline"> Perfil </Link>
                </div>
            </div>
        </template>

        <!-- Destaque: calendário + NR-1 -->
        <div class="mt-2 grid gap-4 lg:grid-cols-3">
            <div
                v-if="can('calendario_estrategico', 'view') && upcomingCalendar?.length && nextCalendarItem"
                class="dashboard-hero"
                :class="can('pesquisas', 'view') ? '' : 'lg:col-span-3'"
            >
                <div class="dashboard-hero-blob -left-10 bottom-0 h-32 w-32 bg-white/15" />
                <div class="relative">
                    <p class="text-xs font-bold uppercase tracking-wider text-white/90">Próximo no calendário</p>
                    <h3 class="mt-2 font-serif text-xl font-bold leading-snug sm:text-2xl">{{ nextCalendarItem.title }}</h3>
                    <p class="mt-2 text-sm text-white/95" :title="formatDateTitle(nextCalendarItem.occurs_on)">
                        {{ formatRelative(nextCalendarItem.occurs_on) }}
                    </p>
                    <p class="mt-1 text-xs text-white/85">{{ kindLabel(nextCalendarItem.kind) }}</p>
                    <Link
                        :href="route('client.strategic-calendar.index')"
                        class="mt-5 inline-flex h-11 w-11 items-center justify-center rounded-full bg-white text-talents-700 shadow-lg transition hover:scale-105"
                        aria-label="Abrir calendário"
                    >
                        <svg class="h-5 w-5 translate-x-px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </Link>
                </div>
            </div>

            <div
                v-if="can('pesquisas', 'view')"
                class="dashboard-panel-dark"
                :class="can('calendario_estrategico', 'view') && upcomingCalendar?.length ? 'lg:col-span-2' : 'lg:col-span-3'"
            >
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Última campanha NR-1</p>
                    <h3 class="mt-2 text-xl font-semibold text-white sm:text-2xl">
                        {{ lastSurvey?.title ?? 'Nenhuma campanha ainda' }}
                    </h3>
                    <div v-if="overallRisk" class="mt-4 flex flex-wrap items-center gap-3">
                        <span class="rounded-full bg-white/10 px-3 py-1 text-sm font-semibold text-white ring-1 ring-white/20">
                            Média {{ Number(overallRisk.average_score).toFixed(2) }} / 5
                        </span>
                        <HealthBadge :risk-level="overallRisk.risk_level" />
                    </div>
                    <div v-if="lastCampaign.completion_rate !== null && lastCampaign.completion_rate !== undefined" class="mt-5 max-w-md">
                        <p class="text-xs font-medium text-slate-400">Taxa de conclusão das respostas</p>
                        <ProgressBar
                            class="mt-2"
                            label="Conclusão"
                            dark
                            :value="lastCampaign.completion_rate"
                            :display-value="`${Number(lastCampaign.completion_rate).toFixed(1)}%`"
                            bar-class="bg-emerald-400"
                        />
                    </div>
                </div>
                <div class="flex shrink-0 flex-col gap-2">
                    <Link
                        v-if="lastSurvey"
                        :href="route('client.surveys.results', lastSurvey.id)"
                        class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-talents-900 shadow-sm transition hover:bg-slate-100"
                    >
                        Ver resultados
                    </Link>
                    <Link
                        v-if="can('pesquisas', 'view')"
                        :href="route('client.surveys.index')"
                        class="inline-flex items-center justify-center rounded-xl border border-white/30 px-4 py-2 text-sm font-semibold text-white/95 hover:bg-white/10"
                    >
                        Todas as pesquisas
                    </Link>
                </div>
            </div>
            <div v-if="!lastSurvey" class="mt-6 rounded-xl border border-white/20 bg-white/5 px-4 py-5 text-sm text-slate-200">
                <p class="font-medium text-white">Comece criando uma campanha</p>
                <p class="mt-1 text-slate-300">Assim que existir uma pesquisa, o resumo de risco aparece aqui.</p>
                <Link
                    :href="route('client.surveys.index')"
                    class="mt-3 inline-flex text-sm font-semibold text-white underline decoration-white/40 hover:decoration-white"
                >
                    Ir para pesquisas
                </Link>
            </div>
            </div>
        </div>

        <!-- KPIs + seções críticas -->
        <div class="mt-8 grid gap-6 lg:grid-cols-3">
            <div class="grid gap-4 sm:grid-cols-3 lg:col-span-2 lg:grid-cols-3">
                <StatCard
                    v-if="can('pesquisas', 'view')"
                    label="Pesquisas ativas"
                    :value="activeSurveys"
                    :detail-href="route('client.surveys.index')"
                    detail-label="Abrir"
                />
                <StatCard
                    v-if="can('planos_acao', 'view')"
                    label="Itens de plano em aberto"
                    :value="openActionPlanCount"
                    :detail-href="actionPlanHref || route('client.surveys.index')"
                    detail-label="Plano de ação"
                />
                <StatCard
                    v-if="can('denuncias', 'view')"
                    label="Denúncias pendentes"
                    :value="pendingComplaintsCount"
                    :detail-href="route('client.complaints.index')"
                    detail-label="Ver denúncias"
                />
            </div>

            <div v-if="can('pesquisas', 'view')" class="dashboard-panel-compact">
                <SectionHeader title="Dimensões com maior risco" subtitle="Top 3 (última campanha)" />
                <ul v-if="lastCampaign.section_results?.length" class="mt-3 space-y-3">
                    <li v-for="(row, idx) in lastCampaign.section_results" :key="idx" class="dashboard-inset-list-item">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm font-semibold text-slate-900">{{ row.section_name }}</p>
                            <HealthBadge :risk-level="row.risk_level" />
                        </div>
                        <p class="mt-1 text-xs text-slate-600">Média {{ Number(row.average_score).toFixed(1) }}</p>
                    </li>
                </ul>
                <EmptyState
                    v-else
                    class="mt-2 border-0 bg-transparent py-6"
                    title="Sem resultados por seção"
                    description="Disponível após a campanha ter respostas e cálculo de resultados."
                />
            </div>
        </div>

        <!-- Próximos dias + tarefas -->
        <div class="mt-8 grid gap-6 lg:grid-cols-2">
            <div v-if="upcomingCalendar && can('calendario_estrategico', 'view')" class="dashboard-panel-compact">
                <SectionHeader title="Próximos 7 dias" subtitle="Calendário estratégico">
                    <template #action>
                        <Link :href="route('client.strategic-calendar.index')" class="text-xs font-semibold text-talents-700 hover:underline">
                            Ver calendário
                        </Link>
                    </template>
                </SectionHeader>
                <ul v-if="upcomingCalendar.length" class="mt-3 space-y-3 text-sm">
                    <li v-for="item in upcomingCalendar" :key="item.id" class="border-b border-slate-100 pb-3 last:border-0 last:pb-0">
                        <p class="text-xs font-medium text-slate-500" :title="formatDateTitle(item.occurs_on)">
                            {{ formatRelative(item.occurs_on) }}
                        </p>
                        <p class="mt-0.5 font-medium text-slate-900">{{ item.title }}</p>
                        <p class="mt-0.5 text-xs text-talents-700">{{ kindLabel(item.kind) }}</p>
                    </li>
                </ul>
                <EmptyState v-else class="mt-2 border-0 bg-transparent py-6" title="Sem eventos nesta semana" />
            </div>

            <div v-if="can('tarefas', 'view')" class="dashboard-panel-compact">
                <SectionHeader title="Minhas tarefas" subtitle="Atribuídas a si, em aberto">
                    <template #action>
                        <Link :href="route('client.tarefas.index')" class="text-xs font-semibold text-talents-700 hover:underline"> Quadros </Link>
                    </template>
                </SectionHeader>
                <ul v-if="pendingTasks?.length" class="mt-3 space-y-2 text-sm">
                    <li v-for="t in pendingTasks" :key="t.id" class="dashboard-inset-row">
                        <p class="font-medium text-slate-900">{{ t.title }}</p>
                        <p class="text-xs text-slate-500">
                            <span v-if="t.list_title">{{ t.list_title }}</span>
                            <span v-if="t.due_date" :title="formatDateTitle(t.due_date)">
                                · vence {{ formatRelative(t.due_date) }}
                            </span>
                        </p>
                    </li>
                </ul>
                <EmptyState v-else class="mt-2 border-0 bg-transparent py-6" title="Sem tarefas atribuídas" />
            </div>
        </div>

        <!-- Plano de ação itens -->
        <div v-if="can('planos_acao', 'view') && openActionPlanItems?.length" class="dashboard-panel-compact mt-8">
            <SectionHeader title="Próximos itens do plano de ação" subtitle="Em aberto na sua empresa">
                <template #action>
                    <Link v-if="actionPlanHref" :href="actionPlanHref" class="text-xs font-semibold text-talents-700 hover:underline">
                        Abrir plano
                    </Link>
                </template>
            </SectionHeader>
            <ul class="mt-3 divide-y divide-slate-100 text-sm">
                <li v-for="it in openActionPlanItems" :key="it.id" class="flex flex-wrap items-center justify-between gap-2 py-2">
                    <div>
                        <p class="font-medium text-slate-900">{{ it.title }}</p>
                        <p class="text-xs text-slate-500">{{ it.survey_title }} · {{ it.status === 'in_progress' ? 'Em progresso' : 'Pendente' }}</p>
                    </div>
                    <span
                        v-if="it.due_date"
                        class="text-xs text-slate-500"
                        :title="formatDateTitle(it.due_date)"
                    >
                        Prazo {{ formatRelative(it.due_date) }}
                    </span>
                </li>
            </ul>
        </div>

        <!-- Denúncias link -->
        <div v-if="complaintsPublicUrl && can('denuncias', 'view')" class="dashboard-panel-compact mt-8">
            <SectionHeader title="Canal de denúncias" subtitle="Lei 14.457/2022 — link público para colaboradores" />
            <p class="mt-2 break-all rounded-lg bg-slate-50 p-2 font-mono text-xs text-slate-800">{{ complaintsPublicUrl }}</p>
            <button
                type="button"
                class="mt-3 rounded-lg border border-talents-200 bg-white px-3 py-1.5 text-sm font-medium text-talents-800 hover:bg-talents-50"
                @click="copyDenuncia(complaintsPublicUrl)"
            >
                {{ copied ? 'Copiado!' : 'Copiar link' }}
            </button>
        </div>

        <div v-if="dashboardCalendar && can('calendario_estrategico', 'view')" class="mt-10">
            <StrategicCalendarWidget
                :items="dashboardCalendar.items"
                :year="dashboardCalendar.year"
                :month="dashboardCalendar.month"
                :kind-labels="dashboardCalendar.kindLabels"
                :can-navigate-prev="dashboardCalendar.canNavigatePrev ?? true"
                :can-navigate-next="dashboardCalendar.canNavigateNext ?? true"
                :period-label="dashboardCalendar.visiblePeriod?.label ?? null"
                :navigation-range="dashboardCalendar.visiblePeriod"
                title="Calendário estratégico"
                subtitle="Suas datas e orientações do mês"
                :full-page-href="route('client.strategic-calendar.index')"
                full-page-label="Ver detalhes"
                dashboard-route="client.dashboard"
                completion-enabled
            />
        </div>
    </ClientLayout>
</template>
