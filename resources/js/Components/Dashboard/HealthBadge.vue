<script setup>
import { computed } from 'vue';

const props = defineProps({
    riskLevel: {
        type: String,
        default: '',
    },
    /** Rótulos da campanha, enviados pelo backend (green/yellow/red). */
    bandLabels: {
        type: Object,
        required: true,
    },
});

const label = computed(() => props.bandLabels[props.riskLevel] ?? '—');

const classes = computed(() => {
    if (props.riskLevel === 'green') return 'bg-emerald-100 text-emerald-900 ring-1 ring-emerald-200';
    if (props.riskLevel === 'yellow') return 'bg-amber-100 text-amber-900 ring-1 ring-amber-200';
    if (props.riskLevel === 'red') return 'bg-rose-100 text-rose-900 ring-1 ring-rose-200';
    return 'bg-slate-100 text-slate-700 ring-1 ring-slate-200';
});
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
        :class="classes"
        :aria-label="`Faixa: ${label}`"
    >
        {{ label }}
    </span>
</template>
