<script setup>
import { computed } from 'vue';
import { levelOf } from './nr1Theme';

/**
 * Medidor semicircular do índice (1 a 5) com as três faixas (1,00–2,33, 2,34–3,66 e 3,67–5,00), nos rótulos do método da campanha.
 * A leitura em texto vai no aria-label e abaixo do desenho (nunca só cor).
 */
const props = defineProps({
    score: { type: Number, required: true },
    scoreLabel: { type: String, required: true },
    risk: { type: String, default: 'yellow' },
    bandLabel: { type: String, required: true },
    bandLabels: { type: Object, required: true },
});

const R = 54;
const CX = 70;
const CY = 66;
const toAngle = (value) => Math.PI * (1 - (Math.min(5, Math.max(1, value)) - 1) / 4);
const point = (value, radius = R) => [CX + radius * Math.cos(toAngle(value)), CY - radius * Math.sin(toAngle(value))];
const arc = (from, to) => {
    const [x1, y1] = point(from);
    const [x2, y2] = point(to);

    return `M ${x1.toFixed(2)} ${y1.toFixed(2)} A ${R} ${R} 0 0 1 ${x2.toFixed(2)} ${y2.toFixed(2)}`;
};
const marker = computed(() => point(props.score));
const level = computed(() => levelOf(props.risk));
</script>

<template>
    <figure class="n1-gauge" :aria-label="`Índice geral ${scoreLabel} de 5, faixa ${bandLabel}. Faixas: ${bandLabels.green} de 1,00 a 2,33; ${bandLabels.yellow} de 2,34 a 3,66; ${bandLabels.red} de 3,67 a 5,00.`">
        <svg viewBox="0 0 140 78" role="img" aria-hidden="true">
            <path :d="arc(1, 5)" fill="none" stroke="#ece6f2" stroke-width="11" stroke-linecap="round" />
            <path :d="arc(1.05, 2.33)" fill="none" stroke="#5fb99a" stroke-width="9" stroke-linecap="round" />
            <path :d="arc(2.38, 3.62)" fill="none" stroke="#e8b04e" stroke-width="9" stroke-linecap="round" />
            <path :d="arc(3.71, 4.95)" fill="none" stroke="#e08a99" stroke-width="9" stroke-linecap="round" />
            <circle class="n1-gauge-dot" :cx="marker[0]" :cy="marker[1]" r="6.5" fill="#fff" :stroke="level.color" stroke-width="3.5" />
        </svg>
        <figcaption class="n1-tiny">{{ bandLabels.green }} · {{ bandLabels.yellow }} · {{ bandLabels.red }}</figcaption>
    </figure>
</template>
