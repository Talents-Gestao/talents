<script setup>
import { computed } from 'vue';

const props = defineProps({
    riskLevel: {
        type: String,
        default: '',
    },
});

const label = computed(() => {
    if (props.riskLevel === 'green') return 'Saudável';
    if (props.riskLevel === 'yellow') return 'Atenção';
    if (props.riskLevel === 'red') return 'Crítico';
    return '—';
});

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
        :aria-label="`Nível de saúde: ${label}`"
    >
        {{ label }}
    </span>
</template>
