<script setup>
import { levelOf } from './nr1Theme';

/** Chip de faixa: ícone + texto + cor (nunca só cor). */
defineProps({
    risk: { type: String, required: true },
    label: { type: String, required: true },
});
</script>

<template>
    <span class="n1-pill n1-level" :class="levelOf(risk).key">
        <component :is="levelOf(risk).icon" class="n1-level-icon" aria-hidden="true" />
        {{ label }}
    </span>
</template>
