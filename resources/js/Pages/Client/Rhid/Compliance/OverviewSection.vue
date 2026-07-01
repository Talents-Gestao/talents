<script setup>
import ApexChart from '@/Components/Charts/ApexChart.vue';
import RhidOverviewKpiCards from '@/Components/Rhid/RhidOverviewKpiCards.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    overviewLoading: { type: Boolean, required: true },
    overviewLoadedAt: { type: Object, default: null },
    overviewCalendarRangeLabel: { type: String, required: true },
    overviewPreviousCalendarRangeLabel: { type: String, required: true },
    overviewAdherencePrevious: { type: Object, default: null },
    overviewAdherencePrevLoaded: { type: Boolean, required: true },
    overviewAdherenceDiasMomDelta: { type: [Number, null], default: null },
    overviewAdherenceColabsMomDelta: { type: [Number, null], default: null },
    overviewBankPrevAnchorLabel: { type: String, required: true },
    overviewBankPrevAvgMinutes: { type: [Number, null], default: null },
    overviewBankPrevNumericRowsLength: { type: Number, required: true },
    overviewBankPrevLoaded: { type: Boolean, required: true },
    overviewBankAvgMomDeltaMinutes: { type: [Number, null], default: null },
    overviewJustTotalPrevious: { type: [Number, null], default: null },
    overviewJustAtestadosPrevious: { type: [Number, null], default: null },
    overviewJustNotePrevious: { type: String, default: '' },
    overviewJustPrevLoaded: { type: Boolean, required: true },
    overviewJustTotalMomDelta: { type: [Number, null], default: null },
    overviewJustAtestadosMomDelta: { type: [Number, null], default: null },
    overviewPunchRowsLength: { type: Number, required: true },
    overviewPunchDistinct: { type: Number, required: true },
    overviewPunchPreviewRows: {
        type: Array,
        required: true,
    },
    overviewBankNumericRowsLength: { type: Number, required: true },
    overviewBankNegativeCount: { type: Number, required: true },
    overviewBankAvgMinutes: { type: [Number, null], default: null },
    overviewBankWorstThree: { type: Array, required: true },
    overviewAdherence: { type: [Object, null], default: null },
    /** Contagem para o cartão (rótulo: dias úteis; backend: dias_calendario_distintos) */
    overviewAdherenceDiasCalendario: { type: [Number, null], default: null },
    overviewAdherencePreviousDiasCalendario: { type: [Number, null], default: null },
    overviewAdherenceWorstEntrada: { type: Array, required: true },
    overviewJustTotal: { type: [Number, null], default: null },
    overviewJustAtestados: { type: [Number, null], default: null },
    overviewJustNote: { type: String, default: '' },
    isAdmin: { type: Boolean, required: true },
    formatRhidBankBalanceMinutes: { type: Function, required: true },
    bankDisplayName: { type: Function, required: true },
    bankDisplayValue: { type: Function, required: true },
    rhidPersonId: { type: Function, required: true },
});

const emit = defineEmits([
    'refresh',
    'go-punches-adherence',
    'go-bank',
    'go-justifications',
    'go-espelho',
    'go-collaborators',
]);

/** Paleta principal alinhada ao logo Talents (talents-700/500/accent + dourado highlight) */
const PALETTE = {
    primary: '#4a2070',
    primaryDeep: '#3a1858',
    primary600: '#632a7e',
    primary500: '#7b4fa2',
    accent: '#b388d9',
    soft: '#e8dcf2',
    veryLight: '#f5f0fa',
    gold: '#e8b84a',
    rose: '#e11d48',
    emerald: '#10b981',
    slate500: '#64748b',
    slate700: '#334155',
};

const signedIntTxt = (n) => {
    if (n == null || Number.isNaN(Number(n))) {
        return null;
    }
    const v = Number(n);
    if (v === 0) {
        return '0';
    }
    return v > 0 ? `+${v}` : `${v}`;
};

const isFiniteNumber = (v) => typeof v === 'number' && Number.isFinite(v);

const overviewBankPositiveCount = computed(() => {
    const total = props.overviewBankNumericRowsLength || 0;
    const neg = props.overviewBankNegativeCount || 0;
    return Math.max(total - neg, 0);
});

const overviewJustOtherCount = computed(() => {
    const total = props.overviewJustTotal;
    const atest = props.overviewJustAtestados;
    if (!isFiniteNumber(total) || !isFiniteNumber(atest)) {
        return null;
    }
    return Math.max(total - atest, 0);
});

/** Aderência: usamos colaboradores_com_dados como denominador qualitativo */
const overviewAdherenceColabs = computed(() => {
    const v = props.overviewAdherence?.resumo?.colaboradores_com_dados;
    return isFiniteNumber(v) ? v : 0;
});

/** Score qualitativo (0–100) de cobertura: dias úteis * colabs, normalizado por uma meta de 30. */
const overviewAdherenceScore = computed(() => {
    const dias = props.overviewAdherenceDiasCalendario || 0;
    const colabs = overviewAdherenceColabs.value;
    if (!dias || !colabs) {
        return 0;
    }
    const score = Math.min(100, Math.round((dias / 22) * 100));
    return score;
});

const overviewJustAtestadosPercent = computed(() => {
    const total = props.overviewJustTotal;
    const atest = props.overviewJustAtestados;
    if (!isFiniteNumber(total) || total <= 0 || !isFiniteNumber(atest)) {
        return 0;
    }
    return Math.round((atest / total) * 100);
});

const overviewBankNegativePercent = computed(() => {
    const total = props.overviewBankNumericRowsLength || 0;
    const neg = props.overviewBankNegativeCount || 0;
    if (!total) {
        return 0;
    }
    return Math.round((neg / total) * 100);
});

const overviewBankAvgHHmm = computed(() => {
    if (!isFiniteNumber(props.overviewBankAvgMinutes)) {
        return '—';
    }
    return props.formatRhidBankBalanceMinutes(props.overviewBankAvgMinutes);
});

const overviewBankAvgIsNegative = computed(
    () => isFiniteNumber(props.overviewBankAvgMinutes) && props.overviewBankAvgMinutes < 0,
);

/* ============================ ApexCharts ============================ */

const radialAderenciaChart = computed(() => {
    const score = overviewAdherenceScore.value;
    return {
        series: [score],
        options: {
            chart: {
                type: 'radialBar',
                toolbar: { show: false },
                fontFamily: 'Figtree, sans-serif',
                sparkline: { enabled: true },
            },
            colors: [PALETTE.primary],
            plotOptions: {
                radialBar: {
                    hollow: { size: '62%' },
                    track: { background: PALETTE.soft, strokeWidth: '100%' },
                    dataLabels: {
                        name: {
                            show: true,
                            offsetY: -8,
                            color: PALETTE.slate500,
                            fontSize: '11px',
                            fontWeight: 500,
                        },
                        value: {
                            offsetY: 4,
                            color: PALETTE.primaryDeep,
                            fontSize: '22px',
                            fontWeight: 700,
                            formatter: (val) => `${val}%`,
                        },
                    },
                },
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shade: 'dark',
                    type: 'horizontal',
                    shadeIntensity: 0.4,
                    gradientToColors: [PALETTE.accent],
                    inverseColors: false,
                    opacityFrom: 1,
                    opacityTo: 1,
                    stops: [0, 100],
                },
            },
            stroke: { lineCap: 'round' },
            labels: ['Cobertura'],
        },
    };
});

const donutBankChart = computed(() => {
    const pos = overviewBankPositiveCount.value;
    const neg = props.overviewBankNegativeCount || 0;
    const total = pos + neg;
    return {
        empty: total === 0,
        series: [pos, neg],
        options: {
            chart: {
                type: 'donut',
                toolbar: { show: false },
                fontFamily: 'Figtree, sans-serif',
                events: {
                    dataPointSelection: () => emit('go-bank'),
                },
            },
            labels: ['Positivos', 'Negativos'],
            colors: [PALETTE.primary500, PALETTE.rose],
            stroke: { width: 2, colors: ['#ffffff'] },
            legend: {
                position: 'bottom',
                fontSize: '12px',
                labels: { colors: PALETTE.slate700 },
                markers: { width: 10, height: 10, radius: 12 },
            },
            dataLabels: {
                enabled: true,
                style: { fontSize: '11px', fontWeight: 600 },
                dropShadow: { enabled: false },
                formatter: (val) => `${Math.round(val)}%`,
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '70%',
                        labels: {
                            show: true,
                            name: { color: PALETTE.slate500, fontSize: '11px' },
                            value: {
                                color: PALETTE.primaryDeep,
                                fontSize: '20px',
                                fontWeight: 700,
                                formatter: (val) => `${val}`,
                            },
                            total: {
                                show: true,
                                label: 'Colab. c/ saldo',
                                color: PALETTE.slate500,
                                fontSize: '11px',
                                formatter: (w) => w.globals.seriesTotals.reduce((a, b) => a + b, 0),
                            },
                        },
                    },
                },
            },
            tooltip: { y: { formatter: (v) => `${v} colaborador(es)` } },
        },
    };
});

const donutJustChart = computed(() => {
    const atest = isFiniteNumber(props.overviewJustAtestados) ? props.overviewJustAtestados : 0;
    const others = isFiniteNumber(overviewJustOtherCount.value) ? overviewJustOtherCount.value : 0;
    const total = atest + others;
    return {
        empty: total === 0,
        series: [atest, others],
        options: {
            chart: {
                type: 'donut',
                toolbar: { show: false },
                fontFamily: 'Figtree, sans-serif',
                events: {
                    dataPointSelection: () => emit('go-justifications'),
                },
            },
            labels: ['Atestados', 'Outras'],
            colors: [PALETTE.gold, PALETTE.primary500],
            stroke: { width: 2, colors: ['#ffffff'] },
            legend: {
                position: 'bottom',
                fontSize: '12px',
                labels: { colors: PALETTE.slate700 },
                markers: { width: 10, height: 10, radius: 12 },
            },
            dataLabels: {
                enabled: true,
                style: { fontSize: '11px', fontWeight: 600 },
                formatter: (val) => `${Math.round(val)}%`,
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '70%',
                        labels: {
                            show: true,
                            name: { color: PALETTE.slate500, fontSize: '11px' },
                            value: {
                                color: PALETTE.primaryDeep,
                                fontSize: '20px',
                                fontWeight: 700,
                                formatter: (val) => `${val}`,
                            },
                            total: {
                                show: true,
                                label: 'Total RHID',
                                color: PALETTE.slate500,
                                fontSize: '11px',
                                formatter: () => `${total}`,
                            },
                        },
                    },
                },
            },
            tooltip: { y: { formatter: (v) => `${v} ocorrência(s)` } },
        },
    };
});

/** Horizontal bar — piores saldos (top 3) — sempre negativos como valor absoluto em minutos */
const barWorstBankChart = computed(() => {
    const rows = props.overviewBankWorstThree || [];
    const labels = [];
    const data = [];
    for (const r of rows.slice(0, 5)) {
        const name = props.bankDisplayName(r) || '—';
        labels.push(name.length > 22 ? `${name.slice(0, 22)}…` : name);
        const display = props.bankDisplayValue(r);
        const sign = typeof display === 'string' && display.startsWith('-') ? -1 : 1;
        const hh = typeof display === 'string' ? display.replace('-', '').split(':') : ['0', '0'];
        const minutes = (parseInt(hh[0], 10) || 0) * 60 + (parseInt(hh[1], 10) || 0);
        data.push(sign * minutes);
    }
    return {
        empty: data.length === 0,
        series: [{ name: 'Saldo (min)', data }],
        options: {
            chart: {
                type: 'bar',
                toolbar: { show: false },
                fontFamily: 'Figtree, sans-serif',
                events: {
                    dataPointSelection: () => emit('go-bank'),
                },
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                    borderRadius: 6,
                    barHeight: '60%',
                    distributed: true,
                    colors: { backgroundBarColors: [PALETTE.veryLight], backgroundBarRadius: 6 },
                },
            },
            colors: [PALETTE.rose, '#f87171', '#fb923c', '#fbbf24', '#facc15'],
            dataLabels: {
                enabled: true,
                formatter: (val) => props.formatRhidBankBalanceMinutes(val),
                style: { fontSize: '11px', colors: ['#ffffff'], fontWeight: 600 },
            },
            xaxis: {
                categories: labels,
                labels: {
                    style: { colors: PALETTE.slate500, fontSize: '11px' },
                    formatter: (v) => props.formatRhidBankBalanceMinutes(v),
                },
            },
            yaxis: { labels: { style: { colors: PALETTE.slate700, fontSize: '12px' } } },
            grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
            legend: { show: false },
            tooltip: { y: { formatter: (v) => props.formatRhidBankBalanceMinutes(v) } },
        },
    };
});

/** Horizontal bar — maiores atrasos de entrada (top 5) em minutos */
const barWorstEntradaChart = computed(() => {
    const rows = props.overviewAdherenceWorstEntrada || [];
    const labels = [];
    const data = [];
    for (const r of rows.slice(0, 5)) {
        const name = r?.nome || '—';
        labels.push(name.length > 22 ? `${name.slice(0, 22)}…` : name);
        data.push(Number(r?.total_atraso_entrada_minutos) || 0);
    }
    return {
        empty: data.length === 0,
        series: [{ name: 'Atraso (min)', data }],
        options: {
            chart: {
                type: 'bar',
                toolbar: { show: false },
                fontFamily: 'Figtree, sans-serif',
                events: {
                    dataPointSelection: () => emit('go-punches-adherence'),
                },
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                    borderRadius: 6,
                    barHeight: '60%',
                    distributed: true,
                },
            },
            colors: [PALETTE.primaryDeep, PALETTE.primary, PALETTE.primary600, PALETTE.primary500, PALETTE.accent],
            dataLabels: {
                enabled: true,
                formatter: (val) => `${val} min`,
                style: { fontSize: '11px', colors: ['#ffffff'], fontWeight: 600 },
            },
            xaxis: {
                categories: labels,
                labels: { style: { colors: PALETTE.slate500, fontSize: '11px' } },
            },
            yaxis: { labels: { style: { colors: PALETTE.slate700, fontSize: '12px' } } },
            grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
            legend: { show: false },
            tooltip: { y: { formatter: (v) => `${v} min` } },
        },
    };
});

/* ============================ Helpers de variação ============================ */

/** Cor/ícone para variação numérica simples (positivo = verde, negativo = rosa). */
const trendInfo = (delta, inverse = false) => {
    if (!isFiniteNumber(delta) || delta === 0) {
        return { txt: signedIntTxt(delta), cls: 'text-white/85', arrow: '→' };
    }
    const positive = inverse ? delta < 0 : delta > 0;
    return {
        txt: signedIntTxt(delta),
        cls: positive ? 'text-emerald-200' : 'text-rose-200',
        arrow: delta > 0 ? '↑' : '↓',
    };
};

const trendBank = computed(() => {
    const m = props.overviewBankAvgMomDeltaMinutes;
    if (!isFiniteNumber(m)) {
        return null;
    }
    return {
        txt: props.formatRhidBankBalanceMinutes(m),
        cls: m >= 0 ? 'text-emerald-200' : 'text-rose-200',
        arrow: m > 0 ? '↑' : m < 0 ? '↓' : '→',
    };
});
</script>

<template>
    <div class="space-y-5">
        <!-- Cabeçalho do módulo -->
        <div
            class="flex flex-col gap-3 rounded-2xl border border-talents-100 bg-gradient-to-br from-talents-50 via-white to-white p-4 shadow-sm md:flex-row md:items-center md:justify-between"
        >
            <div class="min-w-0">
                <h2 class="text-base font-semibold text-talents-800">Compliance de Ponto · RHID</h2>
                <p class="mt-0.5 text-xs leading-relaxed text-slate-600">
                    Mês corrente
                    <span class="font-medium text-talents-700">{{ overviewCalendarRangeLabel }}</span>
                    para aderência e justificativas · banco de horas na data de hoje · marcações pela última leitura.
                </p>
                <p class="mt-0.5 text-[11px] leading-relaxed text-slate-500">
                    Comparativo (Δ) usa o mês civil anterior completo
                    ({{ overviewPreviousCalendarRangeLabel }}); banco de horas anterior fixa na referência
                    {{ overviewBankPrevAnchorLabel }} (último dia civil).
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <p v-if="overviewLoadedAt" class="text-[11px] text-slate-500">
                    Atualizado em
                    <span class="font-medium text-slate-700">
                        {{
                            overviewLoadedAt.toLocaleString('pt-BR', {
                                dateStyle: 'short',
                                timeStyle: 'medium',
                            })
                        }}
                    </span>
                </p>
                <PrimaryButton type="button" :disabled="overviewLoading" @click="emit('refresh')">
                    <svg
                        v-if="overviewLoading"
                        class="mr-1.5 h-3.5 w-3.5 animate-spin"
                        viewBox="0 0 24 24"
                        fill="none"
                    >
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity="0.25" />
                        <path
                            d="M4 12a8 8 0 018-8"
                            stroke="currentColor"
                            stroke-width="3"
                            stroke-linecap="round"
                            fill="none"
                        />
                    </svg>
                    {{ overviewLoading ? 'Atualizando…' : 'Atualizar visão geral' }}
                </PrimaryButton>
            </div>
        </div>

        <RhidOverviewKpiCards
            :loading="overviewLoading"
            :interactive="true"
            :overview-punch-rows-length="overviewPunchRowsLength"
            :overview-punch-distinct="overviewPunchDistinct"
            :overview-bank-numeric-rows-length="overviewBankNumericRowsLength"
            :overview-bank-negative-count="overviewBankNegativeCount"
            :overview-bank-avg-minutes="overviewBankAvgMinutes"
            :overview-bank-avg-mom-delta-minutes="overviewBankAvgMomDeltaMinutes"
            :overview-adherence-dias-calendario="overviewAdherenceDiasCalendario"
            :overview-adherence-colabs="overviewAdherenceColabs"
            :overview-adherence-dias-mom-delta="overviewAdherenceDiasMomDelta"
            :overview-adherence-colabs-mom-delta="overviewAdherenceColabsMomDelta"
            :overview-just-total="overviewJustTotal"
            :overview-just-atestados="overviewJustAtestados"
            :overview-just-total-mom-delta="overviewJustTotalMomDelta"
            :overview-just-atestados-mom-delta="overviewJustAtestadosMomDelta"
            :format-rhid-bank-balance-minutes="formatRhidBankBalanceMinutes"
            @go-punches-adherence="emit('go-punches-adherence')"
            @go-bank="emit('go-bank')"
            @go-justifications="emit('go-justifications')"
        />

        <template v-if="!overviewLoading">
            <!-- ============ Linha de gráficos ============ -->
            <div class="grid gap-3 lg:grid-cols-3">
                <!-- Aderência radial -->
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-semibold text-talents-800">Cobertura de aderência</p>
                            <p class="mt-0.5 text-[11px] text-slate-500">
                                Dias úteis com espelho analisado · meta indicativa de 22
                            </p>
                        </div>
                        <span class="rounded-full bg-talents-50 px-2 py-0.5 text-[10px] font-semibold text-talents-700">
                            Mês
                        </span>
                    </div>
                    <ApexChart
                        type="radialBar"
                        height="220"
                        :options="radialAderenciaChart.options"
                        :series="radialAderenciaChart.series"
                    />
                    <div class="mt-2 grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-lg bg-talents-50 p-2 text-talents-800">
                            <p class="text-[10px] font-medium uppercase tracking-wide text-talents-600">Dias úteis</p>
                            <p class="text-lg font-bold tabular-nums">
                                {{ overviewAdherenceDiasCalendario ?? '—' }}
                            </p>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-2 text-slate-700">
                            <p class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Colab. c/ dados</p>
                            <p class="text-lg font-bold tabular-nums">{{ overviewAdherenceColabs || '—' }}</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="mt-3 w-full rounded-md border border-talents-200 bg-talents-50 px-3 py-1.5 text-xs font-semibold text-talents-700 transition hover:bg-talents-100"
                        @click="emit('go-punches-adherence')"
                    >
                        Ver aderência detalhada →
                    </button>
                </div>

                <!-- Donut banco de horas -->
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-semibold text-talents-800">Saldos de banco</p>
                            <p class="mt-0.5 text-[11px] text-slate-500">
                                Distribuição positivo × negativo (hoje)
                            </p>
                        </div>
                        <span
                            v-if="overviewBankNegativePercent > 0"
                            class="rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-semibold text-rose-700"
                        >
                            {{ overviewBankNegativePercent }}% negativos
                        </span>
                    </div>
                    <ApexChart
                        v-if="!donutBankChart.empty"
                        type="donut"
                        height="240"
                        :options="donutBankChart.options"
                        :series="donutBankChart.series"
                    />
                    <p v-else class="mt-6 text-center text-sm text-slate-500">
                        Sem saldos numéricos para a distribuição.
                    </p>
                    <button
                        type="button"
                        class="mt-3 w-full rounded-md border border-talents-200 bg-talents-50 px-3 py-1.5 text-xs font-semibold text-talents-700 transition hover:bg-talents-100"
                        @click="emit('go-bank')"
                    >
                        Abrir banco de horas →
                    </button>
                </div>

                <!-- Donut justificativas -->
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-semibold text-talents-800">Justificativas</p>
                            <p class="mt-0.5 text-[11px] text-slate-500">Atestados × demais (1ª página RHID)</p>
                        </div>
                        <span
                            v-if="overviewJustAtestadosPercent > 0"
                            class="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700"
                        >
                            {{ overviewJustAtestadosPercent }}% atestados
                        </span>
                    </div>
                    <ApexChart
                        v-if="!donutJustChart.empty"
                        type="donut"
                        height="240"
                        :options="donutJustChart.options"
                        :series="donutJustChart.series"
                    />
                    <p v-else class="mt-6 text-center text-sm text-slate-500">
                        Sem ocorrências de justificativas no período.
                    </p>
                    <p v-if="overviewJustNote" class="mt-1 text-[11px] text-amber-700">{{ overviewJustNote }}</p>
                    <button
                        type="button"
                        class="mt-3 w-full rounded-md border border-talents-200 bg-talents-50 px-3 py-1.5 text-xs font-semibold text-talents-700 transition hover:bg-talents-100"
                        @click="emit('go-justifications')"
                    >
                        Ver justificativas →
                    </button>
                </div>
            </div>

            <!-- ============ TOP críticos ============ -->
            <div class="grid gap-3 lg:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-2 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-talents-800">
                                Maiores débitos · banco de horas
                            </p>
                            <p class="mt-0.5 text-[11px] text-slate-500">
                                Top {{ Math.min((overviewBankWorstThree || []).length, 5) }} colaboradores
                            </p>
                        </div>
                        <span class="rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-semibold text-rose-700">
                            Atenção
                        </span>
                    </div>
                    <ApexChart
                        v-if="!barWorstBankChart.empty"
                        type="bar"
                        :height="Math.max(180, (barWorstBankChart.series[0]?.data?.length || 0) * 46 + 20)"
                        :options="barWorstBankChart.options"
                        :series="barWorstBankChart.series"
                    />
                    <p v-else class="mt-4 text-center text-sm text-slate-500">
                        Nenhum saldo negativo na amostra atual.
                    </p>
                    <ul v-if="(overviewBankWorstThree || []).length" class="mt-2 space-y-1 text-xs text-slate-700">
                        <li
                            v-for="(row, wi) in overviewBankWorstThree.slice(0, 3)"
                            :key="wi"
                            class="flex items-center justify-between gap-2 border-t border-slate-100 pt-1"
                        >
                            <Link
                                v-if="rhidPersonId(row) != null"
                                :href="route('client.rhid.collaborators.show', rhidPersonId(row))"
                                class="truncate font-medium text-talents-800 hover:underline"
                            >
                                {{ bankDisplayName(row) }}
                            </Link>
                            <span v-else class="truncate font-medium text-slate-800">{{ bankDisplayName(row) }}</span>
                            <span class="shrink-0 tabular-nums text-rose-700">{{ bankDisplayValue(row) }}</span>
                        </li>
                    </ul>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-2 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-talents-800">
                                Maiores atrasos · entrada
                            </p>
                            <p class="mt-0.5 text-[11px] text-slate-500">
                                Top {{ Math.min((overviewAdherenceWorstEntrada || []).length, 5) }} colaboradores no mês
                            </p>
                        </div>
                        <span
                            class="rounded-full bg-talents-50 px-2 py-0.5 text-[10px] font-semibold text-talents-700"
                        >
                            Aderência
                        </span>
                    </div>
                    <ApexChart
                        v-if="!barWorstEntradaChart.empty"
                        type="bar"
                        :height="Math.max(180, (barWorstEntradaChart.series[0]?.data?.length || 0) * 46 + 20)"
                        :options="barWorstEntradaChart.options"
                        :series="barWorstEntradaChart.series"
                    />
                    <p v-else class="mt-4 text-center text-sm text-slate-500">
                        Sem agregado para este período — importe espelhos e analise na sub-aba Aderência.
                    </p>
                    <ul
                        v-if="(overviewAdherenceWorstEntrada || []).length"
                        class="mt-2 space-y-1 text-xs text-slate-700"
                    >
                        <li
                            v-for="(rw, ri) in overviewAdherenceWorstEntrada.slice(0, 3)"
                            :key="ri"
                            class="flex items-center justify-between gap-2 border-t border-slate-100 pt-1"
                        >
                            <Link
                                v-if="rw.id_person != null"
                                :href="route('client.rhid.collaborators.show', rw.id_person)"
                                class="truncate font-medium text-talents-800 hover:underline"
                            >
                                {{ rw.nome }}
                            </Link>
                            <span v-else class="truncate font-medium text-slate-800">{{ rw.nome }}</span>
                            <span class="shrink-0 tabular-nums text-talents-700">
                                {{ rw.total_atraso_entrada_minutos ?? 0 }} min
                            </span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- ============ Últimas marcações + Atalhos ============ -->
            <div class="grid gap-3 lg:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-2">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-talents-800">Últimas marcações</p>
                            <p class="mt-0.5 text-[11px] text-slate-500">
                                Amostra de {{ overviewPunchRowsLength }} registro(s) ·
                                {{ overviewPunchDistinct }} colaborador(es) distinto(s)
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-md border border-talents-200 bg-talents-50 px-3 py-1 text-xs font-semibold text-talents-700 transition hover:bg-talents-100"
                            @click="emit('go-punches-adherence')"
                        >
                            Aderência ao horário →
                        </button>
                    </div>
                    <ul
                        v-if="overviewPunchPreviewRows.length"
                        class="mt-3 divide-y divide-slate-100 text-sm"
                    >
                        <li
                            v-for="(pr, pi) in overviewPunchPreviewRows"
                            :key="pi"
                            class="flex items-baseline justify-between gap-x-3 py-1.5"
                        >
                            <span class="flex min-w-0 items-center gap-2">
                                <span
                                    class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-talents-100 text-[10px] font-semibold text-talents-700"
                                >
                                    {{ String(pi + 1).padStart(2, '0') }}
                                </span>
                                <Link
                                    v-if="pr.personId != null"
                                    :href="route('client.rhid.collaborators.show', pr.personId)"
                                    class="truncate font-medium text-talents-800 hover:underline"
                                >
                                    {{ pr.nome }}
                                </Link>
                                <span v-else class="truncate font-medium text-slate-800">{{ pr.nome }}</span>
                            </span>
                            <span class="shrink-0 tabular-nums text-[11px] text-slate-500">{{ pr.dataDisplay }}</span>
                        </li>
                    </ul>
                    <p v-else class="mt-4 text-sm text-slate-500">Nenhuma marcação na amostra.</p>
                </div>

                <div
                    class="rounded-2xl border border-talents-200 bg-gradient-to-br from-white via-talents-50 to-white p-4 shadow-sm"
                >
                    <p class="text-sm font-semibold text-talents-800">Atalhos rápidos</p>
                    <p class="mt-0.5 text-[11px] text-slate-500">Pule direto para a ação que precisa</p>
                    <div class="mt-3 grid grid-cols-1 gap-2">
                        <button
                            type="button"
                            class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-talents-300 hover:bg-talents-50"
                            @click="emit('go-espelho')"
                        >
                            <span class="inline-flex items-center gap-2">
                                <span
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-talents-100 text-talents-700"
                                >
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                        <path
                                            fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-11.25a.75.75 0 00-1.5 0v4.59L7.3 9.4a.75.75 0 10-1.1 1.02l3.25 3.5a.75.75 0 001.1 0l3.25-3.5a.75.75 0 10-1.1-1.02l-1.95 2.06V6.75z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </span>
                                Importar espelho / PDF
                            </span>
                            <span class="text-talents-500">→</span>
                        </button>
                        <button
                            type="button"
                            class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-talents-300 hover:bg-talents-50"
                            @click="emit('go-collaborators')"
                        >
                            <span class="inline-flex items-center gap-2">
                                <span
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-talents-100 text-talents-700"
                                >
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                                    </svg>
                                </span>
                                Lista de colaboradores
                            </span>
                            <span class="text-talents-500">→</span>
                        </button>
                        <Link
                            v-if="isAdmin"
                            :href="route('client.rhid.settings.edit')"
                            class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-talents-300 hover:bg-talents-50"
                        >
                            <span class="inline-flex items-center gap-2">
                                <span
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-talents-100 text-talents-700"
                                >
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                        <path
                                            fill-rule="evenodd"
                                            d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </span>
                                Configurar horários
                            </span>
                            <span class="text-talents-500">→</span>
                        </Link>
                    </div>
                    <!-- Mini-comparativo MoM em destaque -->
                    <div class="mt-3 rounded-xl bg-talents-700/95 p-3 text-white shadow-inner">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-talents-100">
                            Mês anterior · referência
                        </p>
                        <p class="mt-1 text-[11px] leading-snug text-white/85">
                            {{ overviewPreviousCalendarRangeLabel }}
                        </p>
                        <div class="mt-2 space-y-1 text-[11px] text-white/90">
                            <p v-if="overviewBankPrevLoaded && overviewBankPrevNumericRowsLength">
                                <span class="font-semibold">Banco:</span>
                                {{ formatRhidBankBalanceMinutes(overviewBankPrevAvgMinutes ?? 0) }} ·
                                {{ overviewBankPrevNumericRowsLength }} colab.
                            </p>
                            <p v-if="overviewAdherencePrevLoaded && overviewAdherencePrevious?.resumo">
                                <span class="font-semibold">Aderência:</span>
                                {{ overviewAdherencePreviousDiasCalendario ?? '—' }} dias ·
                                {{ overviewAdherencePrevious.resumo.colaboradores_com_dados ?? '—' }} colab.
                            </p>
                            <p v-if="overviewJustPrevLoaded && overviewJustTotalPrevious != null">
                                <span class="font-semibold">Justif.:</span>
                                {{ overviewJustTotalPrevious }} total ·
                                {{ overviewJustAtestadosPrevious ?? '—' }} atestados
                            </p>
                            <p
                                v-if="
                                    !overviewBankPrevLoaded &&
                                    !overviewAdherencePrevLoaded &&
                                    !overviewJustPrevLoaded
                                "
                                class="italic text-white/70"
                            >
                                Atualize para carregar a comparação.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
