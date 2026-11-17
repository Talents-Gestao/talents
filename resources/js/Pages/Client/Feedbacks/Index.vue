<script setup>
import FeedbackCompanyPicker from '@/Components/Feedback/FeedbackCompanyPicker.vue';
import FeedbackEmptyState from '@/Components/Feedback/FeedbackEmptyState.vue';
import FeedbackSessionCard from '@/Components/Feedback/FeedbackSessionCard.vue';
import NineBoxMatrix from '@/Components/Feedback/NineBoxMatrix.vue';
import ApexChart from '@/Components/Charts/ApexChart.vue';
import SectionHeader from '@/Components/Dashboard/SectionHeader.vue';
import StatCard from '@/Components/Dashboard/StatCard.vue';
import FeedbacksLayout from '@/Components/Feedback/FeedbacksLayout.vue';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { Head, Link } from '@inertiajs/vue3';
import {
    ChartBarIcon,
    ChatBubbleLeftRightIcon,
    CheckCircleIcon,
    DocumentDuplicateIcon,
    PlusIcon,
} from '@heroicons/vue/24/outline';
import { computed } from 'vue';

const props = defineProps({
    recentSessions: { type: Array, default: () => [] },
    analytics: { type: Object, default: () => ({}) },
    isCompanyAdmin: { type: Boolean, default: false },
    companyPicker: { type: Array, default: null },
    activeCompany: { type: Object, default: null },
    isAdminContext: { type: Boolean, default: false },
});

const needsCompanySelection = computed(
    () => props.isAdminContext && props.companyPicker?.length && !props.activeCompany,
);

const hasNineBox = computed(
    () => props.isCompanyAdmin && (props.analytics.nine_box?.total ?? 0) > 0,
);

const hasCharts = computed(
    () =>
        props.analytics.thermometer?.series?.some((n) => n > 0) ||
        props.analytics.timeline?.series?.length ||
        props.analytics.perceptions?.series?.[0]?.some((n) => n > 0) ||
        hasNineBox.value,
);

const thermometerOptions = computed(() => ({
    chart: { fontFamily: 'Figtree, sans-serif' },
    labels: props.analytics.thermometer?.labels ?? [],
    legend: { position: 'bottom', fontSize: '12px' },
    colors: ['#16a34a', '#4ade80', '#eab308', '#fb923c', '#ef4444'],
    stroke: { width: 0 },
}));

const thermometerSeries = computed(() => props.analytics.thermometer?.series ?? []);

const timelineOptions = computed(() => ({
    chart: { toolbar: { show: false }, fontFamily: 'Figtree, sans-serif', sparkline: { enabled: false } },
    stroke: { curve: 'smooth', width: 3 },
    xaxis: { categories: props.analytics.timeline?.labels ?? [], labels: { style: { fontSize: '11px' } } },
    colors: ['#632a7e'],
    grid: { borderColor: '#f1f5f9' },
}));

const timelineSeries = computed(() => [
    { name: 'Concluídos', data: props.analytics.timeline?.series ?? [] },
]);

const perceptionOptions = computed(() => ({
    chart: { toolbar: { show: false }, fontFamily: 'Figtree, sans-serif' },
    plotOptions: { bar: { borderRadius: 6, columnWidth: '45%' } },
    xaxis: { categories: props.analytics.perceptions?.labels ?? [] },
    colors: ['#632a7e', '#c4b5fd'],
    legend: { position: 'top' },
    grid: { borderColor: '#f1f5f9' },
}));

const perceptionSeries = computed(() => {
    const s = props.analytics.perceptions?.series ?? [];
    return [
        { name: 'Comportamento', data: s[0] ?? [] },
        { name: 'Desempenho', data: s[1] ?? [] },
    ];
});
</script>

<template>
    <Head title="Feedbacks internos" />

    <FeedbacksLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <h2 class="text-xl font-semibold leading-tight text-talents-900">Feedbacks internos</h2>
                    <p class="mt-1 text-pretty text-sm text-slate-600">
                        Alinhamentos estruturados entre líder e colaborador, com assinatura digital.
                    </p>
                    <p
                        v-if="activeCompany"
                        class="mt-2 inline-flex items-center rounded-full bg-talents-50 px-2.5 py-0.5 text-xs font-medium text-talents-800 ring-1 ring-inset ring-talents-200"
                    >
                        Empresa: {{ activeCompany.name }}
                    </p>
                </div>
                <div v-if="!needsCompanySelection" class="flex shrink-0 flex-wrap items-center gap-2">
                    <Link
                        v-if="isCompanyAdmin"
                        :href="feedbackRoute('templates.index')"
                        class="inline-flex min-h-[40px] items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500/50 focus-visible:ring-offset-2"
                    >
                        <DocumentDuplicateIcon class="h-4 w-4" aria-hidden="true" />
                        Modelos
                    </Link>
                    <Link
                        :href="feedbackRoute('sessions.create')"
                        class="inline-flex min-h-[40px] items-center gap-2 rounded-xl bg-talents-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-talents-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500 focus-visible:ring-offset-2"
                    >
                        <PlusIcon class="h-4 w-4" aria-hidden="true" />
                        Novo feedback
                    </Link>
                </div>
            </div>
        </template>

        <FeedbackCompanyPicker
            v-if="needsCompanySelection"
            :companies="companyPicker"
            class="mb-8"
        />

        <template v-else>
            <div
                class="rounded-2xl border border-talents-200/80 bg-gradient-to-br from-talents-50/90 via-white to-white px-6 py-5 shadow-sm"
            >
                <p class="text-xs font-semibold uppercase tracking-wide text-talents-600">Contrato de expectativas</p>
                <h3 class="mt-1 font-serif text-xl font-bold tracking-tight text-talents-900 sm:text-2xl">
                    Desenvolvimento com conversa estruturada
                </h3>
                <p class="mt-1 max-w-2xl text-sm leading-relaxed text-slate-600">
                    Registre conquistas, alinhe expectativas e acompanhe o termômetro da equipe em um só lugar.
                </p>
            </div>

            <FeedbackCompanyPicker
                v-if="isAdminContext && companyPicker?.length"
                :companies="companyPicker"
                :active-company-id="activeCompany?.id"
                compact
                class="mt-6"
            />

            <div class="mt-8 grid gap-4 sm:grid-cols-2">
                <StatCard label="Feedbacks recentes" :value="recentSessions.length" :interactive="false">
                    <template #icon><ChartBarIcon class="h-6 w-6" /></template>
                </StatCard>
                <StatCard label="Feedbacks concluídos" :value="analytics.completed_count ?? 0" :interactive="false">
                    <template #icon><CheckCircleIcon class="h-6 w-6" /></template>
                </StatCard>
            </div>

            <div v-if="hasCharts" class="mt-8 grid gap-6 lg:grid-cols-2">
                <div v-if="thermometerSeries.some((n) => n > 0)" class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                    <SectionHeader title="Termômetro da equipe" subtitle="Distribuição do momento atual" />
                    <ApexChart type="donut" height="280" :options="thermometerOptions" :series="thermometerSeries" />
                </div>
                <div v-if="timelineSeries[0].data.length" class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                    <SectionHeader title="Evolução" subtitle="Feedbacks concluídos ao longo do tempo" />
                    <ApexChart type="area" height="280" :options="timelineOptions" :series="timelineSeries" />
                </div>
                <div
                    v-if="hasNineBox"
                    class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm lg:col-span-2"
                >
                    <SectionHeader
                        title="Percepções"
                        subtitle="Comportamento × desempenho, com direcionamento por quadrante"
                    />
                    <NineBoxMatrix class="mt-4" :nine-box="analytics.nine_box" />
                </div>
                <div
                    v-else-if="perceptionSeries[0].data.some((n) => n > 0)"
                    class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm lg:col-span-2"
                >
                    <SectionHeader title="Percepções da liderança" subtitle="Comportamento vs desempenho" />
                    <ApexChart type="bar" height="300" :options="perceptionOptions" :series="perceptionSeries" />
                </div>
            </div>

            <div v-if="analytics.strengths?.length || analytics.weaknesses?.length" class="mt-8 grid gap-4 md:grid-cols-2">
                <div
                    v-if="analytics.strengths?.length"
                    class="rounded-2xl border border-emerald-100 bg-gradient-to-b from-emerald-50/80 to-white p-5 shadow-sm"
                >
                    <h3 class="text-sm font-semibold text-emerald-900">Pontos fortes recorrentes</h3>
                    <ul class="mt-3 space-y-2">
                        <li
                            v-for="(item, i) in analytics.strengths"
                            :key="i"
                            class="flex items-start gap-2 text-sm text-emerald-900/90"
                        >
                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500" />
                            {{ item }}
                        </li>
                    </ul>
                </div>
                <div
                    v-if="analytics.weaknesses?.length"
                    class="rounded-2xl border border-amber-100 bg-gradient-to-b from-amber-50/80 to-white p-5 shadow-sm"
                >
                    <h3 class="text-sm font-semibold text-amber-900">Atenção na equipe</h3>
                    <ul class="mt-3 space-y-2">
                        <li
                            v-for="(item, i) in analytics.weaknesses"
                            :key="i"
                            class="flex items-start gap-2 text-sm text-amber-900/90"
                        >
                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500" />
                            {{ item }}
                        </li>
                    </ul>
                </div>
            </div>

            <div class="mt-10">
                <SectionHeader title="Sessões recentes" subtitle="Últimos alinhamentos registrados">
                    <template #action>
                        <Link
                            :href="feedbackRoute('sessions.index')"
                            class="rounded-md text-sm font-semibold text-talents-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500/50"
                        >
                            Ver todas as sessões
                        </Link>
                    </template>
                </SectionHeader>

                <div v-if="recentSessions.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <FeedbackSessionCard v-for="s in recentSessions" :key="s.id" :session="s" />
                </div>
                <FeedbackEmptyState
                    v-else
                    :icon="ChatBubbleLeftRightIcon"
                    title="Nenhum feedback registrado ainda"
                    description="Informe o nome do colaborador e abra o primeiro alinhamento."
                >
                    <Link
                        :href="feedbackRoute('sessions.create')"
                        class="inline-flex min-h-[40px] items-center gap-2 rounded-xl bg-talents-700 px-4 py-2 text-sm font-semibold text-white hover:bg-talents-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500 focus-visible:ring-offset-2"
                    >
                        <PlusIcon class="h-4 w-4" aria-hidden="true" />
                        Criar primeiro feedback
                    </Link>
                </FeedbackEmptyState>
            </div>
        </template>
    </FeedbacksLayout>
</template>
