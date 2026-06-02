<script setup>
import { computed, ref } from 'vue';

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

const filteredDeptRadar = computed(() => ({
    chart: { type: 'radar', toolbar: { show: false }, foreColor: '#334155' },
    colors: ['#632a7e'],
    stroke: { width: 2 },
    fill: { opacity: 0.15 },
    xaxis: {
        categories: activeDeptSections.value.map((r) => r.meta?.section_title || 'Dimensão'),
    },
    yaxis: { show: false, min: 0, max: 100 },
    markers: { size: 4 },
    dataLabels: { enabled: true },
}));

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

const radar = computed(() => ({
    chart: { type: 'radar', toolbar: { show: false }, foreColor: '#334155' },
    colors: ['#632a7e'],
    stroke: { width: 2 },
    fill: { opacity: 0.15 },
    xaxis: {
        categories: props.bySection?.map((r) => r.meta?.section_title || 'Dimensão') ?? [],
    },
    yaxis: { show: false, min: 0, max: 100 },
    markers: { size: 4 },
    dataLabels: { enabled: true },
}));

const radarSeries = computed(() => [
    { name: 'Risco médio', data: props.bySection?.map((r) => Number(r.average_score)) ?? [] },
]);

const riskToBarColor = (level) => {
    if (level === 'green') return '#10b981';
    if (level === 'yellow') return '#f59e0b';
    return '#ef4444';
};

const deptBarChart = computed(() => {
    const rows = props.deptOveralls ?? [];
    return {
        chart: { type: 'bar', toolbar: { show: false }, foreColor: '#334155' },
        plotOptions: {
            bar: {
                borderRadius: 4,
                columnWidth: '55%',
                distributed: true,
                dataLabels: { position: 'top' },
            },
        },
        colors: rows.map((r) => riskToBarColor(r.risk_level)),
        dataLabels: { enabled: true, offsetY: -8 },
        xaxis: {
            categories: rows.map((r) => r.department_name),
        },
        yaxis: { min: 0, max: 100, title: { text: 'Risco (0–100)' } },
        legend: { show: false },
        tooltip: { y: { formatter: (val) => `${Number(val).toFixed(1)}` } },
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
        chart: { type: 'bar', toolbar: { show: false }, foreColor: '#334155' },
        plotOptions: { bar: { horizontal: false, columnWidth: '70%' } },
        xaxis: { categories: cats },
        yaxis: { min: 0, max: 100, title: { text: 'Risco (0–100)' } },
        legend: { position: 'bottom' },
        dataLabels: { enabled: false },
        colors: ['#7b4fa2', '#b388d9', '#632a7e', '#4a2070', '#9b6bc4', '#d4b8e4', '#e8dcf2'],
        tooltip: { shared: true, intersect: false },
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

const healthLevelLabel = (level) => {
    if (level === 'green') return 'Situação favorável';
    if (level === 'yellow') return 'Risco intermediário';
    return 'Risco elevado';
};
</script>

<template>
    <div>
        <div
            v-if="departmentFilterOptions.length > 1"
            class="mb-8 rounded-xl border border-talents-100 bg-white p-6 shadow-sm"
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
            class="rounded-xl border border-talents-200 bg-talents-50/40 p-6 shadow-sm"
        >
            <h3 class="text-lg font-semibold text-talents-900">Resultados — {{ selectedDepartmentName }}</h3>
            <p class="mt-1 text-sm text-gray-500">
                {{ activeDeptOverall.respondent_count }} respondente{{ activeDeptOverall.respondent_count === 1 ? '' : 's' }} neste setor.
            </p>
            <div class="mt-4 flex flex-wrap items-center gap-4">
                <span class="text-4xl font-bold text-talents-800">{{ Number(activeDeptOverall.average_score).toFixed(1) }}</span>
                <span class="rounded-full px-3 py-1 text-sm font-medium" :class="healthBadge(activeDeptOverall.risk_level)">
                    {{ healthLevelLabel(activeDeptOverall.risk_level) }}
                </span>
            </div>
        </div>

        <div
            v-if="isDepartmentFiltered && activeDeptSections.length"
            class="mt-8 rounded-xl border border-gray-200 bg-white p-6 shadow-sm"
        >
            <h3 class="text-lg font-semibold text-talents-900">Dimensões — {{ selectedDepartmentName }}</h3>
            <div class="mt-4 h-96">
                <apexchart height="380" :options="filteredDeptRadar" :series="filteredDeptRadarSeries" />
            </div>
        </div>

        <div v-if="!isDepartmentFiltered && overall" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-talents-900">Indicador geral de risco (0–100)</h3>
            <p class="mt-1 text-sm text-gray-500">Quanto maior, maior o risco psicossocial agregado. Faixas: 0–33 favorável, 34–66 intermediário, 67–100 elevado.</p>
            <div class="mt-4 flex flex-wrap items-center gap-4">
                <span class="text-4xl font-bold text-talents-800">{{ Number(overall.average_score).toFixed(1) }}</span>
                <span class="rounded-full px-3 py-1 text-sm font-medium" :class="healthBadge(overall.risk_level)">
                    {{ healthLevelLabel(overall.risk_level) }}
                </span>
                <span class="text-sm text-gray-600">Respondentes: {{ overall.respondent_count }}</span>
            </div>
        </div>

        <div v-if="!isDepartmentFiltered && bySection?.length" class="mt-8 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-talents-900">Dimensões</h3>
            <div class="mt-4 h-96">
                <apexchart height="380" :options="radar" :series="radarSeries" />
            </div>
        </div>

        <div
            v-if="isDepartmentFiltered && !activeQuestionDistributions?.length"
            class="mt-8 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
        >
            Não há respostas registradas para o setor {{ selectedDepartmentName }}.
        </div>

        <div v-if="!isDepartmentFiltered && departmentParticipation?.length" class="mt-8 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-talents-900">Participação por setor</h3>
            <p class="mt-1 text-sm text-gray-500">
                Setores informados pelos respondentes. Gráficos detalhados por setor exigem pelo menos 1 respondente no mesmo setor.
            </p>
            <table class="mt-4 min-w-full border-collapse text-sm">
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
                        <td class="px-3 py-2 text-right text-gray-700">{{ row.respondent_count }}</td>
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

        <div v-if="!isDepartmentFiltered && deptOveralls?.length" class="mt-8 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-talents-900">Risco por setor (média geral)</h3>
            <p class="mt-1 text-sm text-gray-500">
                Setores só aparecem com pelo menos 1 respondente no mesmo setor (anonimato).
            </p>
            <div class="mt-4 h-80">
                <apexchart height="320" :options="deptBarChart" :series="deptBarSeries" />
            </div>
        </div>

        <div
            v-if="!isDepartmentFiltered && deptOveralls?.length && bySection?.length && deptSectionsByDepartment?.length"
            class="mt-8 rounded-xl border border-gray-200 bg-white p-6 shadow-sm"
        >
            <h3 class="text-lg font-semibold text-talents-900">Dimensões por setor (barras agrupadas)</h3>
            <div class="mt-4 min-h-[28rem]">
                <apexchart height="380" :options="deptGroupedBar" :series="deptGroupedSeries" />
            </div>
        </div>

        <div
            v-if="!isDepartmentFiltered && deptOveralls?.length && bySection?.length && deptSectionsByDepartment?.length"
            class="mt-8 overflow-x-auto rounded-xl border border-gray-200 bg-white p-6 shadow-sm"
        >
            <h3 class="text-lg font-semibold text-talents-900">Tabela de risco por setor e dimensão</h3>
            <table class="mt-4 min-w-full border-collapse text-sm">
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
                        <td class="font-medium text-gray-900">{{ row.department_name }}</td>
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
                                {{ Number(scoreForDeptSection(row.department_id, sec.survey_template_section_id).average_score).toFixed(1) }}
                            </span>
                            <span v-else class="text-gray-400">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="activeQuestionDistributions?.length" class="mt-8 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-talents-900">Detalhamento por pergunta</h3>
            <p class="mt-1 text-sm text-gray-500">
                <template v-if="isDepartmentFiltered">
                    Quantidade de votos por opção da escala no setor {{ selectedDepartmentName }}.
                </template>
                <template v-else>
                    Quantidade de votos por opção da escala, no total da campanha.
                </template>
            </p>

            <div v-for="section in activeQuestionDistributions" :key="section.section_id" class="mt-8 first:mt-6">
                <h4 class="text-base font-semibold text-talents-800">{{ section.section_title }}</h4>

                <div
                    v-for="question in section.questions"
                    :key="question.id"
                    class="mt-5 border-t border-gray-100 pt-5 first:border-t-0 first:pt-0"
                >
                    <p class="text-sm font-medium text-gray-900">{{ question.body }}</p>
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
                            <div class="h-5 flex-1 overflow-hidden rounded-full bg-gray-100">
                                <div
                                    class="h-full rounded-full bg-talents-600 transition-all"
                                    :style="{ width: barWidth(question, value) + '%' }"
                                />
                            </div>
                            <span class="w-20 shrink-0 text-right text-xs font-medium text-gray-700">
                                {{ question.counts[value] }} ({{ percentForOption(question, value) }}%)
                            </span>
                        </div>
                    </div>
                    <p v-else class="mt-2 text-xs text-gray-400">Nenhuma resposta ainda.</p>
                </div>
            </div>
        </div>

        <div class="mt-8 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-talents-900">Insights</h3>
            <ul class="mt-4 list-disc space-y-2 pl-5 text-sm text-gray-700">
                <li v-for="i in insights" :key="i.id">{{ i.message }}</li>
                <li v-if="!insights?.length">Nenhum insight gerado ainda.</li>
            </ul>
        </div>

        <div
            v-if="!isDepartmentFiltered && overall && !deptOveralls?.length && departmentParticipation?.length"
            class="mt-8 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
        >
            Os setores já aparecem na tabela acima, mas os gráficos por setor só serão exibidos quando cada setor atingir 1 respondente (regra de anonimato).
        </div>

        <div
            v-if="!isDepartmentFiltered && overall && !deptOveralls?.length && !departmentParticipation?.length"
            class="mt-8 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
        >
            Não há setores informados nas respostas ainda. Peça aos respondentes que selecionem o setor ao responder a pesquisa.
        </div>
    </div>
</template>
