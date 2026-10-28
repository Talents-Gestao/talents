<script setup>
import { CheckCircleIcon, ClockIcon, PencilSquareIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';

/** Chip de estado da campanha: ícone + texto + cor (nunca só cor). Tons: ok (verde), warn (âmbar), gray (cinza). */
const props = defineProps({
    label: { type: String, required: true },
    tone: { type: String, default: 'gray' },
});

const icon = computed(() => ({ ok: CheckCircleIcon, warn: ClockIcon, gray: PencilSquareIcon })[props.tone] ?? PencilSquareIcon);
</script>

<template>
    <span class="n1-status" :class="tone" role="status" :aria-label="`Estado da campanha: ${label}`">
        <component :is="icon" class="n1-status-icon" aria-hidden="true" />
        {{ label }}
    </span>
</template>
