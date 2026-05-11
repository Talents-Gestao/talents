<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, default: '' },
    /** 0–100 */
    value: { type: Number, default: 0 },
    displayValue: { type: String, default: '' },
    barClass: { type: String, default: 'bg-talents-600' },
    /** Estilo para fundos escuros (hero) */
    dark: { type: Boolean, default: false },
});

const pct = computed(() => Math.min(100, Math.max(0, Number(props.value) || 0)));
</script>

<template>
    <div class="min-w-0">
        <div
            class="mb-1 flex items-baseline justify-between gap-2 text-xs"
            :class="dark ? 'text-slate-200' : ''"
        >
            <span :class="dark ? 'text-slate-200' : 'truncate font-medium text-slate-700'">{{ label }}</span>
            <span
                v-if="displayValue !== ''"
                class="shrink-0 tabular-nums"
                :class="dark ? 'text-slate-300' : 'text-slate-500'"
            >{{ displayValue }}</span>
        </div>
        <div
            class="h-2 overflow-hidden rounded-full"
            :class="dark ? 'bg-white/15' : 'bg-slate-100'"
            role="progressbar"
            :aria-valuenow="Math.round(pct)"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-label="label"
        >
            <div class="h-full rounded-full transition-[width] duration-300" :class="barClass" :style="{ width: `${pct}%` }" />
        </div>
    </div>
</template>
