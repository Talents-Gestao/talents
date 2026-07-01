<script setup>
import { defineAsyncComponent } from 'vue';

/**
 * Carrega ApexCharts só quando a página exibe um gráfico (não no bundle global).
 */
const Chart = defineAsyncComponent(() =>
    import('vue3-apexcharts').then((module) => module.default),
);
</script>

<template>
    <Chart v-bind="$attrs" />
</template>
