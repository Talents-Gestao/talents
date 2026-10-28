<script setup>
import { computed, onMounted, ref } from 'vue';
import { reducedMotion } from './nr1Theme';

/** Anel de progresso (ex.: participação). O valor também aparece em texto (aria-label e legenda do cartão). */
const props = defineProps({
    percent: { type: Number, required: true },
    label: { type: String, required: true },
    color: { type: String, default: '#168a80' },
    size: { type: Number, default: 78 },
});

const R = 30;
const C = 2 * Math.PI * R;
const drawn = ref(reducedMotion() ? props.percent : 0);
onMounted(() => {
    if (!reducedMotion()) requestAnimationFrame(() => (drawn.value = props.percent));
});
const offset = computed(() => C * (1 - Math.min(100, Math.max(0, drawn.value)) / 100));
</script>

<template>
    <svg :width="size" :height="size" viewBox="0 0 76 76" role="img" :aria-label="label" class="n1-ring">
        <circle cx="38" cy="38" :r="R" fill="none" stroke="#d7ede9" stroke-width="8" />
        <circle
            cx="38" cy="38" :r="R" fill="none" :stroke="color" stroke-width="8" stroke-linecap="round"
            :stroke-dasharray="C" :stroke-dashoffset="offset" transform="rotate(-90 38 38)" class="n1-ring-arc"
        />
    </svg>
</template>
