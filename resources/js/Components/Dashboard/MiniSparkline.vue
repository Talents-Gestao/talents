<script setup>
import { computed } from 'vue';

const props = defineProps({
    /** Array of numeric values (last point = most recent) */
    series: {
        type: Array,
        default: () => [],
    },
    color: {
        type: String,
        default: '#632a7e',
    },
});

const chartOptions = computed(() => ({
    chart: {
        type: 'area',
        sparkline: { enabled: true },
        animations: { enabled: false },
        toolbar: { show: false },
    },
    stroke: { curve: 'smooth', width: 2, colors: [props.color] },
    fill: {
        type: 'solid',
        opacity: 0.18,
        colors: [props.color],
    },
    dataLabels: { enabled: false },
    xaxis: { labels: { show: false }, axisBorder: { show: false }, axisTicks: { show: false } },
    yaxis: { show: false },
    grid: { show: false },
    tooltip: { enabled: true, theme: 'light', x: { show: false } },
}));

const chartSeries = computed(() => [
    {
        name: 'Valor',
        data: (props.series || []).map((v) => Number(v) || 0),
    },
]);
</script>

<template>
    <div class="h-10 w-full min-w-[4rem]">
        <apexchart v-if="series?.length" type="area" height="40" :options="chartOptions" :series="chartSeries" />
    </div>
</template>
