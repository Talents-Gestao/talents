<script setup>
import { computed } from 'vue';

/** Monograma quadrado arredondado: logo da empresa, se existir; senão as iniciais sobre o gradiente da marca. */
const props = defineProps({
    name: { type: String, required: true },
    logoUrl: { type: String, default: null },
    size: { type: Number, default: 46 },
});

const initials = computed(() => {
    const words = props.name.replace(/\(.*?\)/g, '').trim().split(/\s+/).filter((word) => word.length > 2 || /^[A-Za-zÀ-ÿ]/.test(word));
    const letters = words.length > 1 ? words[0][0] + words[1][0] : (words[0] ?? '?').slice(0, 2);

    return letters.toUpperCase();
});
</script>

<template>
    <span class="n1-avatar" :style="{ width: size + 'px', height: size + 'px', fontSize: Math.round(size * 0.38) + 'px' }" aria-hidden="true">
        <img v-if="logoUrl" :src="logoUrl" alt="" />
        <template v-else>{{ initials }}</template>
    </span>
</template>
