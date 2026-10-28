<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { reducedMotion } from './nr1Theme';

/**
 * Conta do zero até o valor ao entrar na tela. `value` vem já formatado pelo back-end (ex.: «2,60», «83,3%»):
 * extraímos o número e as casas decimais e reaplicamos o mesmo formato. Com prefers-reduced-motion, mostra direto.
 */
const props = defineProps({
    value: { type: String, required: true },
    duration: { type: Number, default: 900 },
});

const shown = ref(props.value);
let frame = null;

const parse = (text) => {
    const match = String(text).match(/^([^\d-]*)(-?\d+(?:[.,]\d+)?)(.*)$/);
    if (!match) return null;
    const decimals = (match[2].split(/[.,]/)[1] ?? '').length;

    return { prefix: match[1], number: parseFloat(match[2].replace(',', '.')), decimals, suffix: match[3] };
};

const run = () => {
    const parsed = parse(props.value);
    if (!parsed || reducedMotion()) {
        shown.value = props.value;

        return;
    }
    const start = performance.now();
    const tick = (now) => {
        const t = Math.min(1, (now - start) / props.duration);
        const eased = 1 - Math.pow(1 - t, 3);
        shown.value = parsed.prefix + (parsed.number * eased).toFixed(parsed.decimals).replace('.', ',') + parsed.suffix;
        if (t < 1) frame = requestAnimationFrame(tick);
        else shown.value = props.value;
    };
    frame = requestAnimationFrame(tick);
};

onMounted(run);
watch(() => props.value, run);
onBeforeUnmount(() => frame && cancelAnimationFrame(frame));
</script>

<template>
    <span :aria-label="value">{{ shown }}</span>
</template>
