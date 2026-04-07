<script setup>
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    survey: Object,
    completedCount: Number,
    bySection: Array,
    radar: Object,
});

const chartOptions = computed(() => ({
    chart: { toolbar: { show: false }, fontFamily: 'Figtree, sans-serif' },
    colors: ['#632a7e'],
    plotOptions: {
        radar: {
            polygons: {
                strokeColors: '#d4b8e4',
                connectorColors: '#e8dcf2',
            },
        },
    },
    xaxis: {
        categories: props.radar?.labels?.length ? props.radar.labels : ['—'],
    },
    yaxis: { show: false, max: 5 },
    dataLabels: { enabled: true },
}));

const chartSeries = computed(() => [
    {
        name: 'Média',
        data: props.radar?.series?.length ? props.radar.series : [0],
    },
]);
</script>

<template>
    <Head :title="'Resultados — ' + survey.title" />

    <ClientLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-talents-900">Resultados</h2>
                <div class="flex flex-wrap gap-2">
                    <a
                        :href="route('client.metodologia.pesquisa-satisfacao.export.csv', survey.id)"
                        class="rounded-md border border-talents-300 bg-white px-3 py-2 text-sm font-medium text-talents-800 hover:bg-talents-50"
                    >
                        Exportar CSV
                    </a>
                    <Link
                        :href="route('client.metodologia.pesquisa-satisfacao.show', survey.id)"
                        class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Voltar
                    </Link>
                </div>
            </div>
        </template>

        <p class="text-sm text-gray-600">{{ survey.title }} · {{ completedCount }} respostas concluídas</p>

        <div v-if="radar?.labels?.length" class="mt-6 rounded-xl border border-talents-100 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-talents-800">Visão por dimensão (média 0–5)</h3>
            <apexchart type="radar" height="360" :options="chartOptions" :series="chartSeries" />
        </div>

        <div v-for="block in bySection" :key="block.section.id" class="mt-8 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-talents-900">{{ block.section.title }}</h3>
            <p v-if="block.section.description" class="mt-1 text-sm text-gray-600">{{ block.section.description }}</p>

            <div v-if="block.section_average != null" class="mt-4">
                <p class="text-xs font-medium uppercase text-gray-500">Média da seção</p>
                <div class="mt-1 h-3 w-full max-w-md overflow-hidden rounded-full bg-gray-100">
                    <div
                        class="h-full rounded-full bg-talents-500 transition-all"
                        :style="{ width: Math.min(100, (block.section_average / 5) * 100) + '%' }"
                    />
                </div>
                <p class="mt-1 text-sm font-semibold text-talents-800">{{ block.section_average }} / 5</p>
            </div>

            <ul class="mt-6 space-y-4">
                <li v-for="row in block.scales" :key="row.question.id" class="border-b border-gray-100 pb-4 last:border-0">
                    <p class="text-sm font-medium text-gray-900">{{ row.question.body }}</p>
                    <p class="mt-1 text-sm text-gray-600">
                        Média: <strong>{{ row.average != null ? row.average : '—' }}</strong> (n = {{ row.count }})
                    </p>
                </li>
            </ul>

            <div v-for="open in block.open" :key="open.question.id" class="mt-6">
                <h4 class="text-sm font-semibold text-gray-800">{{ open.question.body }}</h4>
                <ul class="mt-2 max-h-60 space-y-2 overflow-y-auto text-sm text-gray-700">
                    <li v-for="(a, i) in open.answers" :key="i" class="rounded-lg bg-gray-50 px-3 py-2">{{ a }}</li>
                    <li v-if="!open.answers.length" class="text-gray-500">Nenhuma resposta ainda.</li>
                </ul>
            </div>
        </div>
    </ClientLayout>
</template>
