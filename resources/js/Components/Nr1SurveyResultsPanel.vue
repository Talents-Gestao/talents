<script setup>
import ApexChart from '@/Components/Charts/ApexChart.vue';
import { ChevronDownIcon } from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    survey: Object,
    overall: Object,
    bySection: Array,
    deptOveralls: Array,
    deptSectionsByDepartment: Array,
    insights: Array,
    questionDistributions: { type: Array, default: () => [] },
    departmentParticipation: { type: Array, default: () => [] },
    questionDistributionsByDepartment: { type: Array, default: () => [] },
});

const likertScaleMax = 5;
const likertScaleMin = 1;

/** Médias Likert em PT-BR (vírgula decimal), com casas fixas. */
const formatLikertScore = (value, digits = 2) => {
    const n = Number(value);
    if (!Number.isFinite(n)) {
        return '—';
    }
    return n.toLocaleString('pt-BR', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    });
};

/** Eixo 1–5: inteiros limpos (evita 1.000 do ApexCharts). */
const formatLikertAxisTick = (value) => {
    const n = Number(value);
    if (!Number.isFinite(n)) {
        return '';
    }
    if (Math.abs(n - Math.round(n)) < 1e-9) {
        return String(Math.round(n));
    }
    return formatLikertScore(n, 1);
};

const selectedDepartmentId = ref('');

const departmentFilterOptions = computed(() => [
    { id: '', name: 'Todos os setores (visão geral)' },
    ...(props.departmentParticipation ?? []).map((d) => ({
        id: d.department_id,
        name: d.department_name,
    })),
]);

const normalizedSelectedDepartmentId = computed(() => {
    const value = selectedDepartmentId.value;
    if (value === '' || value === null || value === undefined) {
        return null;
    }
    return Number(value);
});

const isDepartmentFiltered = computed(() => normalizedSelectedDepartmentId.value !== null);

const selectedDepartmentName = computed(() => {
    if (!isDepartmentFiltered.value) {
        return null;
    }
    return (
        departmentFilterOptions.value.find((o) => Number(o.id) === normalizedSelectedDepartmentId.value)
            ?.name ?? 'Setor'
    );
});

const activeDeptOverall = computed(() => {
    if (!isDepartmentFiltered.value) {
        return null;
    }
    return (
        props.deptOveralls?.find((d) => d.department_id === normalizedSelectedDepartmentId.value) ?? null
    );
});

const activeDeptSections = computed(() => {
    if (!isDepartmentFiltered.value) {
        return [];
    }
    const group = props.deptSectionsByDepartment?.find(
        (g) => g.department_id === normalizedSelectedDepartmentId.value,
    );
    return group?.sections ?? [];
});

const activeQuestionDistributions = computed(() => {
    if (!isDepartmentFiltered.value) {
        return props.questionDistributions ?? [];
    }
    const row = props.questionDistributionsByDepartment?.find(
        (d) => d.department_id === normalizedSelectedDepartmentId.value,
    );
    return row?.sections ?? [];
});

const riskToBarColor = (level) => {
    if (level === 'green') return '#10b981';
    if (level === 'yellow') return '#f59e0b';
    return '#ef4444';
};

const riskLevelFromScore = (score) => {
    const value = Number(score);
    if (value <= 2.33) return 'green';
    if (value <= 3.66) return 'yellow';
    return 'red';
};

const resolveRiskLevel = (row) => row?.risk_level || riskLevelFromScore(row?.average_score);

const healthLevelLabel = (level) => {
    if (level === 'green') return 'Situação favorável';
    if (level === 'yellow') return 'Risco intermediário';
    return 'Risco elevado';
};

const buildDimensionRadarOptions = (sections) => {
    const rows = sections ?? [];
    const discreteMarkers = rows.map((row, dataPointIndex) => {
        const color = riskToBarColor(resolveRiskLevel(row));
        return {
            seriesIndex: 0,
            dataPointIndex,
            fillColor: color,
            strokeColor: color,
            size: 10,
        };
    });

    return {
        chart: {
            type: 'radar',
            toolbar: { show: false },
            foreColor: '#334155',
            animations: { enabled: true, speed: 700 },
            fontFamily: 'inherit',
        },
        colors: ['#64748b'],
        stroke: { width: 2.5, colors: ['#94a3b8'] },
        fill: { opacity: 0.12, colors: ['#cbd5e1'] },
        plotOptions: {
            radar: {
                size: 210,
                polygons: {
                    strokeColors: '#e2e8f0',
                    connectorColors: '#e2e8f0',
                    fill: { colors: ['#f8fafc', '#ffffff'] },
                },
            },
        },
        xaxis: {
            categories: rows.map((r) => r.meta?.section_title || 'Dimensão'),
            labels: {
                style: { fontSize: '12px', fontWeight: 600, colors: ['#334155'] },
            },
        },
        yaxis: {
            show: true,
            min: likertScaleMin,
            max: likertScaleMax,
            tickAmount: 4,
            forceNiceScale: false,
            decimalsInFloat: 0,
            labels: {
                formatter: formatLikertAxisTick,
                style: { fontSize: '11px', colors: ['#64748b'] },
            },
        },
        markers: {
            size: 6,
            strokeWidth: 2,
            strokeColors: '#fff',
            hover: { size: 12 },
            discrete: discreteMarkers,
        },
        dataLabels: {
            enabled: true,
            formatter: (value) => formatLikertScore(value, 2),
            style: {
                fontSize: '12px',
                fontWeight: 700,
                colors: rows.map(() => '#ffffff'),
            },
            background: {
                enabled: true,
                borderRadius: 6,
                padding: 8,
                opacity: 1,
                borderWidth: 0,
                dropShadow: { enabled: false },
                backgroundColor: rows.map((row) => riskToBarColor(resolveRiskLevel(row))),
            },
        },
        tooltip: {
            y: {
                formatter: (value, { dataPointIndex }) => {
                    const row = rows[dataPointIndex];
                    const level = resolveRiskLevel(row);
                    return `${formatLikertScore(value, 2)} · ${healthLevelLabel(level)}`;
                },
            },
        },
        legend: { show: false },
    };
};

const filteredDeptRadar = computed(() => buildDimensionRadarOptions(activeDeptSections.value));

const filteredDeptRadarSeries = computed(() => [
    {
        name: selectedDepartmentName.value ?? 'Setor',
        data: activeDeptSections.value.map((r) => Number(r.average_score)),
    },
]);

const frequencyLabels = {
    1: 'Nunca',
    2: 'Raramente',
    3: 'Às vezes',
    4: 'Frequentemente',
    5: 'Sempre',
};

const agreementLabels = {
    1: 'Discordo totalmente',
    2: 'Discordo',
    3: 'Neutro',
    4: 'Concordo',
    5: 'Concordo totalmente',
};

const labelForOption = (responseScale, value) => {
    const labels = responseScale === 'agreement' ? agreementLabels : frequencyLabels;
    return labels[value] ?? String(value);
};

const percentForOption = (question, value) => {
    if (!question.total) {
        return 0;
    }
    return Math.round((question.counts[value] / question.total) * 100);
};

const barWidth = (question, value) => {
    if (!question.total) {
        return 0;
    }
    return Math.max((question.counts[value] / question.total) * 100, question.counts[value] > 0 ? 2 : 0);
};

const radar = computed(() => buildDimensionRadarOptions(props.bySection ?? []));

const radarSeries = computed(() => [
    { name: 'Risco médio', data: props.bySection?.map((r) => Number(r.average_score)) ?? [] },
]);

const dimensionRowClass = (level) => {
    if (level === 'green') return 'border-emerald-200 bg-emerald-50/80';
    if (level === 'yellow') return 'border-amber-200 bg-amber-50/80';
    return 'border-red-200 bg-red-50/80';
};

const dimensionScoreClass = (level) => {
    if (level === 'green') return 'bg-emerald-600 text-white';
    if (level === 'yellow') return 'bg-amber-500 text-white';
    return 'bg-red-500 text-white';
};

const deptBarChart = computed(() => {
    const rows = props.deptOveralls ?? [];
    return {
        chart: {
            type: 'bar',
            toolbar: { show: false },
            foreColor: '#334155',
            animations: { enabled: true, speed: 600 },
            fontFamily: 'inherit',
        },
        plotOptions: {
            bar: {
                borderRadius: 6,
                columnWidth: '58%',
                distributed: true,
                dataLabels: { position: 'top' },
            },
        },
        colors: rows.map((r) => riskToBarColor(r.risk_level)),
        dataLabels: {
            enabled: true,
            offsetY: -18,
            formatter: (val) => formatLikertScore(val, 2),
            style: { fontSize: '12px', fontWeight: 700, colors: ['#0f172a'] },
        },
        grid: {
            borderColor: '#e2e8f0',
            strokeDashArray: 4,
            padding: { top: 12 },
        },
        xaxis: {
            categories: rows.map((r) => r.department_name),
            labels: { style: { fontSize: '12px', fontWeight: 600, colors: '#475569' } },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: {
            min: likertScaleMin,
            max: likertScaleMax,
            tickAmount: 4,
            forceNiceScale: false,
            decimalsInFloat: 0,
            title: { text: 'Média (1–5)', style: { fontSize: '12px', fontWeight: 600, color: '#64748b' } },
            labels: { formatter: formatLikertAxisTick },
        },
        legend: { show: false },
        tooltip: { y: { formatter: (val) => formatLikertScore(val, 2) } },
    };
});

const deptBarSeries = computed(() => [
    {
        name: 'Média de risco',
        data: (props.deptOveralls ?? []).map((r) => Number(r.average_score)),
    },
]);

const deptGroupedBar = computed(() => {
    const depts = props.deptOveralls ?? [];
    const sections = props.bySection ?? [];
    const cats = depts.map((d) => d.department_name);
    const series = sections.map((sec) => {
        const sid = sec.survey_template_section_id;
        return {
            name: sec.meta?.section_title || 'Dimensão',
            data: depts.map((d) => {
                const grp = props.deptSectionsByDepartment?.find((g) => g.department_id === d.department_id);
                const row = grp?.sections?.find((s) => s.survey_template_section_id === sid);
                return row != null ? Number(row.average_score) : null;
            }),
        };
    });
    return {
        chart: {
            type: 'bar',
            toolbar: { show: false },
            foreColor: '#334155',
            animations: { enabled: true, speed: 650 },
            fontFamily: 'inherit',
        },
        plotOptions: { bar: { horizontal: false, columnWidth: '68%', borderRadius: 3 } },
        xaxis: {
            categories: cats,
            labels: { style: { fontSize: '12px', fontWeight: 600, colors: '#475569' } },
        },
        yaxis: {
            min: likertScaleMin,
            max: likertScaleMax,
            tickAmount: 4,
            forceNiceScale: false,
            decimalsInFloat: 0,
            title: { text: 'Média (1–5)', style: { fontSize: '12px', fontWeight: 600, color: '#64748b' } },
            labels: { formatter: formatLikertAxisTick },
        },
        legend: { position: 'bottom', fontSize: '12px', fontWeight: 600, markers: { radius: 10 } },
        dataLabels: { enabled: false },
        colors: ['#7b4fa2', '#b388d9', '#632a7e', '#4a2070', '#9b6bc4', '#d4b8e4', '#e8dcf2'],
        grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
        tooltip: {
            shared: true,
            intersect: false,
            y: { formatter: (val) => (val == null ? '—' : formatLikertScore(val, 2)) },
        },
    };
});

const deptGroupedSeries = computed(() => {
    const depts = props.deptOveralls ?? [];
    const sections = props.bySection ?? [];
    return sections.map((sec) => {
        const sid = sec.survey_template_section_id;
        return {
            name: sec.meta?.section_title || 'Dimensão',
            data: depts.map((d) => {
                const grp = props.deptSectionsByDepartment?.find((g) => g.department_id === d.department_id);
                const row = grp?.sections?.find((s) => s.survey_template_section_id === sid);
                return row != null ? Number(row.average_score) : null;
            }),
        };
    });
});

const heatmapCellClass = (level) => {
    if (level === 'green') return 'bg-emerald-100 text-emerald-900';
    if (level === 'yellow') return 'bg-amber-100 text-amber-900';
    return 'bg-red-100 text-red-900';
};

const scoreForDeptSection = (departmentId, sectionId) => {
    const grp = props.deptSectionsByDepartment?.find((g) => g.department_id === departmentId);
    return grp?.sections?.find((s) => s.survey_template_section_id === sectionId) ?? null;
};

const healthBadge = (level) => {
    if (level === 'green') return 'bg-emerald-100 text-emerald-800';
    if (level === 'yellow') return 'bg-amber-100 text-amber-800';
    return 'bg-red-100 text-red-800';
};

/** Barras por opção Likert: intensidade de risco (1 baixo → 5 alto). */
const optionBarClass = (value) => {
    if (value <= 2) return 'bg-emerald-500';
    if (value === 3) return 'bg-amber-400';
    if (value === 4) return 'bg-orange-500';
    return 'bg-red-500';
};

const questionAverage = (question) => {
    if (!question?.total) {
        return null;
    }
    let weighted = 0;
    for (const value of [1, 2, 3, 4, 5]) {
        weighted += value * (question.counts?.[value] ?? 0);
    }
    return weighted / question.total;
};

const showCriticalQuestionsOnly = ref(false);

const filteredQuestionDistributions = computed(() => {
    const sections = activeQuestionDistributions.value ?? [];
    if (!showCriticalQuestionsOnly.value) {
        return sections;
    }
    return sections
        .map((section) => ({
            ...section,
            questions: (section.questions ?? []).filter((q) => {
                const avg = questionAverage(q);
                return avg != null && avg > 2.33;
            }),
        }))
        .filter((section) => section.questions.length > 0);
});

const openQuestionSections = ref({});

watch(
    filteredQuestionDistributions,
    (sections) => {
        const next = { ...openQuestionSections.value };
        for (const section of sections ?? []) {
            if (next[section.section_id] === undefined) {
                next[section.section_id] = false;
            }
        }
        openQuestionSections.value = next;
    },
    { immediate: true },
);

const toggleQuestionSection = (sectionId) => {
    openQuestionSections.value = {
        ...openQuestionSections.value,
        [sectionId]: !openQuestionSections.value[sectionId],
    };
};

const expandAllQuestionSections = () => {
    const next = {};
    for (const section of filteredQuestionDistributions.value ?? []) {
        next[section.section_id] = true;
    }
    openQuestionSections.value = next;
};

const collapseAllQuestionSections = () => {
    const next = {};
    for (const section of filteredQuestionDistributions.value ?? []) {
        next[section.section_id] = false;
    }
    openQuestionSections.value = next;
};

const sectionQuestionMeta = (section) => {
    const count = section.questions?.length ?? 0;
    return `${count} pergunta${count === 1 ? '' : 's'}`;
};
</script>

<template>
    <div class="space-y-10">
        <nav
            class="sticky top-2 z-20 flex flex-wrap gap-2 rounded-2xl border border-talents-200/70 bg-white/95 px-3 py-2.5 text-xs font-semibold text-slate-600 shadow-sm backdrop-blur"
            aria-label="Navegação dos resultados"
        >
            <span class="mr-1 hidden items-center text-[10px] font-bold uppercase tracking-wide text-talents-700 sm:inline-flex">
                Ir para
            </span>
            <a href="#nr1-indicador" class="rounded-full bg-talents-50 px-3 py-1.5 text-talents-900 ring-1 ring-talents-200 hover:bg-talents-100">Indicador</a>
            <a href="#nr1-dimensoes" class="rounded-full bg-talents-50 px-3 py-1.5 text-talents-900 ring-1 ring-talents-200 hover:bg-talents-100">Radar · dimensões</a>
            <a href="#nr1-setores" class="rounded-full bg-talents-50 px-3 py-1.5 text-talents-900 ring-1 ring-talents-200 hover:bg-talents-100">Gráficos por setor</a>
            <a href="#nr1-perguntas" class="rounded-full px-3 py-1.5 text-slate-600 hover:bg-slate-100">Perguntas</a>
            <a href="#nr1-insights" class="rounded-full px-3 py-1.5 text-slate-600 hover:bg-slate-100">Insights</a>
        </nav>

        <div class="rounded-2xl border border-talents-200/60 bg-gradient-to-r from-talents-50/80 via-white to-white px-5 py-4 shadow-sm sm:px-6">
            <p class="text-xs font-bold uppercase tracking-wider text-talents-700">Painel visual</p>
            <h3 class="mt-1 text-xl font-semibold text-slate-900">Indicadores e gráficos principais</h3>
            <p class="mt-1 max-w-3xl text-sm text-slate-600">
                Leitura rápida do risco geral, radar de dimensões e comparativos por setor — mesmos dados da pesquisa, com destaque visual.
            </p>
        </div>

        <div
            v-if="departmentFilterOptions.length > 1"
            class="rounded-2xl border border-talents-100 bg-white p-5 shadow-sm sm:p-6"
        >
            <label for="department-filter" class="text-sm font-semibold text-talents-900">Filtrar por setor</label>
            <p class="mt-1 text-sm text-gray-500">
                Selecione um setor para ver indicadores e respostas específicas daquele departamento.
            </p>
            <select
                id="department-filter"
                v-model="selectedDepartmentId"
                class="mt-3 block w-full max-w-md rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
            >
                <option v-for="opt in departmentFilterOptions" :key="String(opt.id || 'all')" :value="opt.id">
                    {{ opt.name }}
                </option>
            </select>
        </div>

        <div
            v-if="isDepartmentFiltered && activeDeptOverall"
            id="nr1-indicador"
            class="scroll-mt-28 overflow-hidden rounded-2xl border border-talents-200 bg-gradient-to-br from-talents-50 via-white to-white shadow-md ring-1 ring-talents-100"
        >
            <div class="border-b border-talents-100/80 px-6 py-4">
                <p class="text-[11px] font-bold uppercase tracking-wider text-talents-700">Indicador · setor</p>
                <h3 class="mt-1 text-lg font-semibold text-talents-900">Resultados — {{ selectedDepartmentName }}</h3>
                <p class="mt-1 text-sm text-gray-500">
                    {{ activeDeptOverall.respondent_count }} respondente{{ activeDeptOverall.respondent_count === 1 ? '' : 's' }} neste setor.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-4 px-6 py-6">
                <span class="text-5xl font-bold tracking-tight text-talents-800 tabular-nums">{{ formatLikertScore(activeDeptOverall.average_score) }}</span>
                <span class="rounded-full px-3 py-1.5 text-sm font-semibold" :class="healthBadge(activeDeptOverall.risk_level)">
                    {{ healthLevelLabel(activeDeptOverall.risk_level) }}
                </span>
            </div>
        </div>

        <div
            v-if="isDepartmentFiltered && activeDeptSections.length"
            id="nr1-dimensoes"
            class="scroll-mt-28 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-md ring-1 ring-slate-100"
        >
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/80 px-6 py-4">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-talents-700">Gráfico · radar</p>
                    <h3 class="mt-1 text-lg font-semibold text-talents-900">Dimensões — {{ selectedDepartmentName }}</h3>
                </div>
                <div class="flex flex-wrap gap-3 text-xs text-gray-600">
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500" /> Favorável</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-amber-500" /> Intermediário</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-red-500" /> Elevado</span>
                </div>
            </div>
            <div class="grid gap-8 p-4 sm:p-6 xl:grid-cols-5">
                <div class="min-h-[36rem] rounded-xl bg-gradient-to-b from-slate-50 to-white p-2 xl:col-span-3">
                    <ApexChart height="560" :options="filteredDeptRadar" :series="filteredDeptRadarSeries" />
                </div>
                <ul class="space-y-2 xl:col-span-2">
                    <li
                        v-for="row in activeDeptSections"
                        :key="row.survey_template_section_id"
                        class="flex items-center justify-between gap-3 rounded-xl border px-3 py-3 shadow-sm"
                        :class="dimensionRowClass(resolveRiskLevel(row))"
                    >
                        <span class="text-sm font-medium text-gray-900">{{ row.meta?.section_title || 'Dimensão' }}</span>
                        <span
                            class="shrink-0 rounded-md px-2.5 py-1 text-sm font-bold tabular-nums"
                            :class="dimensionScoreClass(resolveRiskLevel(row))"
                        >
                            {{ formatLikertScore(row.average_score) }}
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        <div
            v-if="!isDepartmentFiltered && overall"
            id="nr1-indicador"
            class="scroll-mt-28 overflow-hidden rounded-2xl border border-talents-200 bg-gradient-to-br from-talents-50 via-white to-white shadow-md ring-1 ring-talents-100"
        >
            <div class="border-b border-talents-100/80 px-6 py-4">
                <p class="text-[11px] font-bold uppercase tracking-wider text-talents-700">Indicador geral</p>
                <h3 class="mt-1 text-lg font-semibold text-talents-900">Indicador geral de risco (1–5)</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Média ponderada das respostas Likert. Quanto maior, maior o risco. Faixas: 1,00–2,33 favorável · 2,34–3,66 intermediário · 3,67–5,00 elevado.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-4 px-6 py-6">
                <span class="text-5xl font-bold tracking-tight text-talents-800 tabular-nums">{{ formatLikertScore(overall.average_score) }}</span>
                <span class="rounded-full px-3 py-1.5 text-sm font-semibold" :class="healthBadge(overall.risk_level)">
                    {{ healthLevelLabel(overall.risk_level) }}
                </span>
                <span class="text-sm font-medium text-gray-600">Respondentes: {{ overall.respondent_count }}</span>
            </div>
        </div>

        <div
            v-if="!isDepartmentFiltered && bySection?.length"
            id="nr1-dimensoes"
            class="scroll-mt-28 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-md ring-1 ring-slate-100"
        >
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/80 px-6 py-4">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-talents-700">Gráfico · radar</p>
                    <h3 class="mt-1 text-lg font-semibold text-talents-900">Dimensões</h3>
                </div>
                <div class="flex flex-wrap gap-3 text-xs text-gray-600">
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500" /> Favorável (1,00–2,33)</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-amber-500" /> Intermediário (2,34–3,66)</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-red-500" /> Elevado (3,67–5,00)</span>
                </div>
            </div>
            <div class="grid gap-8 p-4 sm:p-6 xl:grid-cols-5">
                <div class="min-h-[36rem] rounded-xl bg-gradient-to-b from-slate-50 to-white p-2 xl:col-span-3">
                    <ApexChart height="560" :options="radar" :series="radarSeries" />
                </div>
                <ul class="space-y-2 xl:col-span-2">
                    <li
                        v-for="row in bySection"
                        :key="row.survey_template_section_id"
                        class="flex items-center justify-between gap-3 rounded-xl border px-3 py-3 shadow-sm"
                        :class="dimensionRowClass(resolveRiskLevel(row))"
                    >
                        <span class="text-sm font-medium text-gray-900">{{ row.meta?.section_title || 'Dimensão' }}</span>
                        <span
                            class="shrink-0 rounded-md px-2.5 py-1 text-sm font-bold tabular-nums"
                            :class="dimensionScoreClass(resolveRiskLevel(row))"
                        >
                            {{ formatLikertScore(row.average_score) }}
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        <div
            v-if="isDepartmentFiltered && !activeQuestionDistributions?.length"
            class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
        >
            Não há respostas registradas para o setor {{ selectedDepartmentName }}.
        </div>

        <div
            v-if="!isDepartmentFiltered && departmentParticipation?.length"
            id="nr1-setores"
            class="scroll-mt-28 space-y-6"
        >
            <div class="rounded-2xl border border-talents-200/60 bg-gradient-to-r from-talents-50/80 via-white to-white px-5 py-4 shadow-sm sm:px-6">
                <p class="text-xs font-bold uppercase tracking-wider text-talents-700">Por setor</p>
                <h3 class="mt-1 text-xl font-semibold text-slate-900">Participação e gráficos de risco</h3>
                <p class="mt-1 max-w-2xl text-sm text-slate-600">
                    Comparativos dinâmicos por departamento, gerados a partir das respostas agregadas.
                </p>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 bg-slate-50/70 px-6 py-3">
                    <h4 class="text-sm font-semibold text-talents-900">Participação por setor</h4>
                    <p class="mt-0.5 text-xs text-gray-500">
                        Setores informados pelos respondentes. Gráficos detalhados por setor exigem pelo menos 1 respondente no mesmo setor.
                    </p>
                </div>
                <div class="overflow-x-auto p-4 sm:p-6">
                    <table class="min-w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50">
                                <th class="px-3 py-2 text-left font-medium text-gray-700">Setor</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-700">Respondentes</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-700">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in departmentParticipation"
                                :key="row.department_id"
                                class="border-b border-gray-100"
                            >
                                <td class="px-3 py-2 font-medium text-gray-900">{{ row.department_name }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-gray-700">{{ row.respondent_count }}</td>
                                <td class="px-3 py-2">
                                    <span
                                        v-if="row.meets_minimum"
                                        class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800"
                                    >
                                        Exibido nos gráficos
                                    </span>
                                    <span
                                        v-else
                                        class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800"
                                    >
                                        Aguardando mínimo (1)
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div
            v-if="!isDepartmentFiltered && deptOveralls?.length"
            :id="departmentParticipation?.length ? undefined : 'nr1-setores'"
            class="scroll-mt-28 overflow-hidden rounded-2xl border border-talents-200/80 bg-white shadow-md ring-1 ring-talents-100"
        >
            <div class="border-b border-talents-100 bg-gradient-to-r from-talents-50/90 to-white px-6 py-4">
                <p class="text-[11px] font-bold uppercase tracking-wider text-talents-700">Gráfico · barras</p>
                <h3 class="mt-1 text-lg font-semibold text-talents-900">Risco por setor (média geral)</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Setores só aparecem com pelo menos 1 respondente no mesmo setor (anonimato). Cores conforme faixa de risco.
                </p>
            </div>
            <div class="bg-gradient-to-b from-slate-50/80 to-white p-4 sm:p-6">
                <div class="min-h-[26rem] rounded-xl border border-slate-100 bg-white p-2 shadow-inner sm:min-h-[28rem]">
                    <ApexChart height="420" :options="deptBarChart" :series="deptBarSeries" />
                </div>
            </div>
        </div>

        <div
            v-if="!isDepartmentFiltered && deptOveralls?.length && bySection?.length && deptSectionsByDepartment?.length"
            class="overflow-hidden rounded-2xl border border-talents-200/80 bg-white shadow-md ring-1 ring-talents-100"
        >
            <div class="border-b border-talents-100 bg-gradient-to-r from-talents-50/90 to-white px-6 py-4">
                <p class="text-[11px] font-bold uppercase tracking-wider text-talents-700">Gráfico · barras agrupadas</p>
                <h3 class="mt-1 text-lg font-semibold text-talents-900">Dimensões por setor</h3>
                <p class="mt-1 text-sm text-gray-500">Comparação lado a lado das dimensões em cada setor.</p>
            </div>
            <div class="bg-gradient-to-b from-slate-50/80 to-white p-4 sm:p-6">
                <div class="min-h-[30rem] rounded-xl border border-slate-100 bg-white p-2 shadow-inner sm:min-h-[32rem]">
                    <ApexChart height="460" :options="deptGroupedBar" :series="deptGroupedSeries" />
                </div>
            </div>
        </div>

        <div
            v-if="!isDepartmentFiltered && deptOveralls?.length && bySection?.length && deptSectionsByDepartment?.length"
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
        >
            <div class="border-b border-slate-100 bg-slate-50/70 px-6 py-3">
                <h3 class="text-sm font-semibold text-talents-900">Tabela de risco por setor e dimensão</h3>
                <p class="mt-0.5 text-xs text-gray-500">Valores numéricos complementares aos gráficos acima.</p>
            </div>
            <div class="overflow-x-auto p-4 sm:p-6">
                <table class="min-w-full border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50">
                            <th class="px-3 py-2 text-left font-medium text-gray-700">Setor</th>
                            <th
                                v-for="sec in bySection"
                                :key="sec.survey_template_section_id"
                                class="px-2 py-2 text-center font-medium text-gray-700"
                            >
                                {{ sec.meta?.section_title || 'Dimensão' }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in deptOveralls"
                            :key="row.department_id"
                            class="border-b border-gray-100"
                        >
                            <td class="px-3 py-2 font-medium text-gray-900">{{ row.department_name }}</td>
                            <td
                                v-for="sec in bySection"
                                :key="sec.survey_template_section_id + '-' + row.department_id"
                                class="px-2 py-2 text-center"
                            >
                                <span
                                    v-if="scoreForDeptSection(row.department_id, sec.survey_template_section_id)"
                                    class="inline-block min-w-[3rem] rounded px-2 py-1 font-mono text-xs"
                                    :class="heatmapCellClass(scoreForDeptSection(row.department_id, sec.survey_template_section_id).risk_level)"
                                >
                                    {{ formatLikertScore(scoreForDeptSection(row.department_id, sec.survey_template_section_id).average_score) }}
                                </span>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div
            v-if="activeQuestionDistributions?.length"
            id="nr1-perguntas"
            class="scroll-mt-28 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Detalhe analítico</p>
                    <h3 class="mt-1 text-lg font-semibold text-talents-900">Detalhamento por pergunta</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        <template v-if="isDepartmentFiltered">
                            Votos por opção da escala no setor {{ selectedDepartmentName }}. Expanda cada dimensão para ver o detalhe.
                        </template>
                        <template v-else>
                            Votos por opção da escala na campanha. Expanda cada dimensão para ver o detalhe.
                        </template>
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700">
                        <input v-model="showCriticalQuestionsOnly" type="checkbox" class="rounded border-gray-300 text-talents-700 focus:ring-talents-500" />
                        Só intermediário/elevado
                    </label>
                    <button
                        type="button"
                        class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                        @click="expandAllQuestionSections"
                    >
                        Expandir tudo
                    </button>
                    <button
                        type="button"
                        class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                        @click="collapseAllQuestionSections"
                    >
                        Recolher tudo
                    </button>
                </div>
            </div>

            <p
                v-if="showCriticalQuestionsOnly && !filteredQuestionDistributions.length"
                class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900"
            >
                Nenhuma pergunta com média acima de 2,33 neste filtro.
            </p>

            <div class="mt-6 space-y-3">
                <div
                    v-for="section in filteredQuestionDistributions"
                    :key="section.section_id"
                    class="overflow-hidden rounded-xl border border-slate-200/90 bg-slate-50/40"
                >
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition hover:bg-white/80"
                        :aria-expanded="!!openQuestionSections[section.section_id]"
                        @click="toggleQuestionSection(section.section_id)"
                    >
                        <div class="min-w-0">
                            <h4 class="text-sm font-semibold text-talents-900">{{ section.section_title }}</h4>
                            <p class="mt-0.5 text-xs text-slate-500">{{ sectionQuestionMeta(section) }}</p>
                        </div>
                        <ChevronDownIcon
                            class="h-5 w-5 shrink-0 text-talents-500 transition-transform duration-200"
                            :class="openQuestionSections[section.section_id] ? 'rotate-180' : ''"
                            aria-hidden="true"
                        />
                    </button>

                    <div
                        v-show="openQuestionSections[section.section_id]"
                        class="space-y-5 border-t border-slate-200/80 bg-white px-4 py-4"
                    >
                        <div
                            v-for="question in section.questions"
                            :key="question.id"
                            class="border-b border-slate-100 pb-5 last:border-b-0 last:pb-0"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <p class="text-sm font-medium text-gray-900">{{ question.body }}</p>
                                <span
                                    v-if="questionAverage(question) != null"
                                    class="shrink-0 rounded-md px-2 py-0.5 text-xs font-semibold tabular-nums"
                                    :class="dimensionScoreClass(riskLevelFromScore(questionAverage(question)))"
                                >
                                    {{ formatLikertScore(questionAverage(question)) }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">
                                {{ question.total }} resposta{{ question.total === 1 ? '' : 's' }}
                                <span v-if="question.response_scale === 'agreement'"> · Escala de concordância</span>
                                <span v-else> · Escala de frequência</span>
                            </p>

                            <div v-if="question.total" class="mt-3 space-y-2">
                                <div v-for="value in [1, 2, 3, 4, 5]" :key="value" class="flex items-center gap-3">
                                    <span class="w-36 shrink-0 text-xs text-gray-600 sm:w-44">
                                        {{ labelForOption(question.response_scale, value) }}
                                    </span>
                                    <div class="h-4 flex-1 overflow-hidden rounded-full bg-gray-100">
                                        <div
                                            class="h-full rounded-full transition-all"
                                            :class="optionBarClass(value)"
                                            :style="{ width: barWidth(question, value) + '%' }"
                                        />
                                    </div>
                                    <span class="w-20 shrink-0 text-right text-xs font-medium tabular-nums text-gray-700">
                                        {{ question.counts[value] }} ({{ percentForOption(question, value) }}%)
                                    </span>
                                </div>
                            </div>
                            <p v-else class="mt-2 text-xs text-gray-400">Nenhuma resposta ainda.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="nr1-insights" class="scroll-mt-28 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Síntese</p>
            <h3 class="mt-1 text-lg font-semibold text-talents-900">Insights</h3>
            <ul class="mt-4 list-disc space-y-2 pl-5 text-sm text-gray-700">
                <li v-for="i in insights" :key="i.id">{{ i.message }}</li>
                <li v-if="!insights?.length">Nenhum insight gerado ainda.</li>
            </ul>
        </div>

        <div
            v-if="!isDepartmentFiltered && overall && !deptOveralls?.length && departmentParticipation?.length"
            class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
        >
            Os setores já aparecem na tabela acima, mas os gráficos por setor só serão exibidos quando cada setor atingir 1 respondente (regra de anonimato).
        </div>

        <div
            v-if="!isDepartmentFiltered && overall && !deptOveralls?.length && !departmentParticipation?.length"
            class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
        >
            Não há setores informados nas respostas ainda. Peça aos respondentes que selecionem o setor ao responder a pesquisa.
        </div>
    </div>
</template>
