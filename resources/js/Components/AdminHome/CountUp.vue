<script setup>
import { useHomeIntro } from '@/composables/useHomeIntro';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    value: { type: Number, default: 0 },
    /** Formata o número exibido (padrão: inteiro pt-BR). */
    format: {
        type: Function,
        default: (n) => Math.round(n).toLocaleString('pt-BR'),
    },
    durationMs: { type: Number, default: 900 },
});

const animate = useHomeIntro();
const shown = ref(animate ? 0 : props.value);
let frame = null;

const stop = () => {
    if (frame !== null) {
        cancelAnimationFrame(frame);
        frame = null;
    }
};

onMounted(() => {
    if (!animate || props.value === 0) {
        shown.value = props.value;
        return;
    }
    const target = props.value;
    const start = performance.now();
    const tick = (now) => {
        const t = Math.min(1, (now - start) / props.durationMs);
        shown.value = target * (1 - (1 - t) ** 3);
        frame = t < 1 ? requestAnimationFrame(tick) : null;
    };
    frame = requestAnimationFrame(tick);
});

// Atualizações de dados depois da entrada trocam o valor direto, sem nova animação.
watch(
    () => props.value,
    (next) => {
        stop();
        shown.value = next;
    },
);

onBeforeUnmount(stop);
</script>

<template>
    <span class="home-countup" :style="{ minWidth: `${format(value).length}ch` }">{{ format(shown) }}</span>
</template>
