<script setup>
import { dimensionMeta } from './nr1Theme';

/** Ícone colorido da dimensão (mesma cor e ícone em todas as telas do shell). */
defineProps({
    title: { type: String, required: true },
    size: { type: Number, default: 30 },
});
</script>

<template>
    <span
        class="n1-dim-badge"
        :style="{ background: dimensionMeta(title).tint, color: dimensionMeta(title).color, width: size + 'px', height: size + 'px' }"
        aria-hidden="true"
    >
        <component :is="dimensionMeta(title).icon" :style="{ width: size * 0.6 + 'px', height: size * 0.6 + 'px' }" />
    </span>
</template>
