<script setup>
defineProps({
    /** Inclui as faixas numéricas ao lado do rótulo. */
    withRanges: { type: Boolean, default: true },
});
</script>

<template>
    <div class="flex flex-wrap gap-3 text-xs text-gray-600">
        <span class="inline-flex items-center gap-1.5">
            <span class="h-2.5 w-2.5 rounded-full bg-emerald-500" />
            Leve<span v-if="withRanges"> (1,00–2,33)</span>
        </span>
        <span class="inline-flex items-center gap-1.5">
            <span class="h-2.5 w-2.5 rounded-full bg-amber-500" />
            Intermediário<span v-if="withRanges"> (2,34–3,66)</span>
        </span>
        <span class="inline-flex items-center gap-1.5">
            <span class="h-2.5 w-2.5 rounded-full bg-red-500" />
            Grave<span v-if="withRanges"> (3,67–5,00)</span>
        </span>
    </div>
</template>
