<script setup>
defineProps({
    /** Rótulos da campanha, enviados pelo backend (green/yellow/red). */
    labels: { type: Object, required: true },
    /** Inclui as faixas numéricas ao lado do rótulo. */
    withRanges: { type: Boolean, default: true },
});
</script>

<template>
    <div class="flex flex-wrap gap-2 text-xs">
        <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-emerald-800">
            <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-emerald-500" />
            <span class="font-semibold">{{ labels.green }}</span>
            <span v-if="withRanges" class="font-medium text-emerald-700/80">(1,00–2,33)</span>
        </span>
        <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-amber-900">
            <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-amber-500" />
            <span class="font-semibold">{{ labels.yellow }}</span>
            <span v-if="withRanges" class="font-medium text-amber-800/80">(2,34–3,66)</span>
        </span>
        <span class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-red-800">
            <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-red-500" />
            <span class="font-semibold">{{ labels.red }}</span>
            <span v-if="withRanges" class="font-medium text-red-700/80">(3,67–5,00)</span>
        </span>
    </div>
</template>
