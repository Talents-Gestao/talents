<script setup>
import ApexChart from '@/Components/Charts/ApexChart.vue';
import PontoPeopleSnapshot from '@/Components/Rhid/PontoPeopleSnapshot.vue';
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
    overviewMonthlyCompliance: { type: [Object, null], default: null },
    overviewMonthlyComplianceMonth: { type: String, required: true },
    overviewMonthlyComplianceLoading: { type: Boolean, required: true },
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
    'monthly-compliance-month-change',
    'go-punches-adherence',
    'go-bank',
    'go-justifications',
    'go-espelho',
    'go-collaborators',
]);

const PALETTE = {
    veryLight: '#f5f0fa',
    rose: '#e11d48',
    slate500: '#64748b',
    slate700: '#334155',
};

const overviewMonthlyComplianceMonthMax = computed(() => {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}`;
});

const onMonthlyComplianceMonthInput = (value) => {
    emit('monthly-compliance-month-change', value);
};

const overviewPeople = computed(() => props.overviewMonthlyCompliance?.people ?? null);

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
</script>

<template>
    <div class="space-y-5">
        <!-- Cabeçalho do módulo -->
        <div
            class="flex flex-col gap-3 rounded-2xl border border-talents-100 bg-gradient-to-br from-talents-50 via-white to-white p-4 shadow-sm md:flex-row md:items-center md:justify-between"
        >
            <div class="min-w-0">
                <h2 class="text-base font-semibold text-talents-800">Gestão de ponto</h2>
                <p class="mt-0.5 text-xs leading-relaxed text-slate-600">
                    Pontualidade, faltas não justificadas e atestados no mês
                    <span class="font-medium text-talents-700">{{ overviewCalendarRangeLabel }}</span>
                    · a partir dos espelhos importados.
                </p>
                <p class="mt-0.5 text-[11px] leading-relaxed text-slate-500">
                    Banco de horas e marcações ficam abaixo, como apoio operacional.
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

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <PontoPeopleSnapshot
                :people="overviewPeople"
                :loading="overviewMonthlyComplianceLoading || overviewLoading"
                :month-label="overviewCalendarRangeLabel"
                :show-month-picker="true"
                :month-value="overviewMonthlyComplianceMonth"
                :month-max="overviewMonthlyComplianceMonthMax"
                :month-disabled="overviewLoading || overviewMonthlyComplianceLoading"
                :link-collaborators="true"
                @month-change="onMonthlyComplianceMonthInput"
            />
        </div>

        <template v-if="!overviewLoading">
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
