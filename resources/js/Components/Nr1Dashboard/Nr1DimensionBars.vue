<script setup>
import { bandBadgeClass, bandBarClass } from '@/utils/nr1Bands';

defineProps({
    dimensions: { type: Array, default: () => [] },
    legend: { type: String, default: '' },
});
</script>

<template>
    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="nr1-dimensions-title">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="nr1-dimensions-title" class="text-base font-semibold text-talents-900">Indicadores das dimensões</h2>
            <p class="text-xs text-gray-500">Escala de 1 a 5</p>
        </div>

        <p v-if="!dimensions.length" class="mt-4 text-sm text-gray-600">Os indicadores aparecem depois que as respostas forem calculadas.</p>

        <ul v-else class="mt-4 space-y-4">
            <li v-for="dimension in dimensions" :key="dimension.section_id">
                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                    <span class="font-medium text-gray-900">{{ dimension.title }}</span>
                    <span class="flex items-center gap-2">
                        <span class="font-semibold text-talents-900">{{ dimension.score_label }}</span>
                        <span class="inline-flex rounded-md border px-2 py-0.5 text-xs font-semibold" :class="bandBadgeClass(dimension.risk_level)">
                            {{ dimension.band_label }}
                        </span>
                    </span>
                </div>
                <div
                    class="mt-1.5 h-2 w-full rounded-full bg-gray-100"
                    role="img"
                    :aria-label="`${dimension.title}: ${dimension.score_label} de 5, ${dimension.band_label}`"
                >
                    <div class="h-2 rounded-full" :class="bandBarClass(dimension.risk_level)" :style="{ width: `${dimension.bar_percent}%` }" />
                </div>
            </li>
        </ul>

        <p v-if="legend" class="mt-4 text-xs text-gray-500">{{ legend }}</p>
    </section>
</template>
