<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import MiniSparkline from './MiniSparkline.vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    hint: { type: String, default: '' },
    /** e.g. +12.5 for +12.5% */
    deltaPercent: { type: Number, default: null },
    /** When set, overrides automatic delta line (e.g. conversion in p.p.) */
    deltaTextOverride: { type: String, default: '' },
    sparklineSeries: { type: Array, default: null },
    detailHref: { type: String, default: '' },
    detailLabel: { type: String, default: 'Ver detalhes' },
});

const deltaClass = computed(() => {
    if (props.deltaTextOverride && (props.deltaPercent === null || props.deltaPercent === undefined)) {
        return 'text-slate-600';
    }
    if (props.deltaPercent === null || props.deltaPercent === undefined) return 'text-slate-400';
    if (props.deltaPercent > 0) return 'text-emerald-600';
    if (props.deltaPercent < 0) return 'text-rose-600';
    return 'text-slate-500';
});

const deltaText = computed(() => {
    if (props.deltaTextOverride) return props.deltaTextOverride;
    if (props.deltaPercent === null || props.deltaPercent === undefined) return '';
    const sign = props.deltaPercent > 0 ? '+' : '';
    return `${sign}${props.deltaPercent.toFixed(1)}% vs período anterior`;
});
</script>

<template>
    <div class="surface-card flex min-h-[7.5rem] flex-col justify-between border-slate-200/70 p-5 text-slate-900">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ label }}</p>
                <p class="mt-1.5 text-2xl font-bold tabular-nums tracking-tight text-slate-900">{{ value }}</p>
                <p v-if="hint" class="mt-1 text-xs text-slate-500">{{ hint }}</p>
                <p v-if="deltaText" class="mt-1 text-xs font-medium" :class="deltaClass">{{ deltaText }}</p>
            </div>
            <div v-if="$slots.icon" class="shrink-0 text-talents-600 opacity-90">
                <slot name="icon" />
            </div>
        </div>
        <div class="mt-3 flex min-h-[2.5rem] items-end justify-between gap-2">
            <MiniSparkline v-if="sparklineSeries?.length" :series="sparklineSeries" class="min-w-0 flex-1" />
            <Link
                v-if="detailHref"
                :href="detailHref"
                class="ml-auto shrink-0 self-end text-xs font-semibold text-talents-700 hover:text-talents-800 hover:underline"
            >
                {{ detailLabel }}
            </Link>
        </div>
    </div>
</template>
