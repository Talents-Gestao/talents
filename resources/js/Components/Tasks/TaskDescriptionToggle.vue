<script setup>
import { ChevronDownIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';
import { descriptionToggleLabel, showsFilledIndicator } from './taskDescriptionToggle.js';

/**
 * Botão só com ícone que abre e fecha a descrição de uma etapa. O painel fica fora (aria-controls aponta para ele) e
 * só é ocultado (v-show): o que foi digitado continua no campo e é salvo normalmente.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    /** A etapa já tem descrição: rótulo «Ver descrição» e ponto indicador quando recolhida. */
    filled: { type: Boolean, default: false },
    /** id do painel da descrição. */
    controls: { type: String, required: true },
});

const emit = defineEmits(['toggle']);

const label = computed(() => descriptionToggleLabel(props));
const indicator = computed(() => showsFilledIndicator(props));
</script>

<template>
    <button
        type="button"
        class="relative inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-md transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500 motion-reduce:transition-none"
        :class="filled ? 'text-talents-700' : 'text-slate-400 hover:text-slate-600'"
        :aria-expanded="open ? 'true' : 'false'"
        :aria-controls="controls"
        :aria-label="label"
        :title="label"
        data-description-toggle
        @click.stop="emit('toggle')"
        @pointerdown.stop
    >
        <ChevronDownIcon
            class="h-4 w-4 transition-transform duration-150 motion-reduce:transition-none"
            :class="open ? 'rotate-180' : ''"
            aria-hidden="true"
        />
        <span
            v-if="indicator"
            class="absolute right-0.5 top-0.5 h-1.5 w-1.5 rounded-full bg-talents-600"
            aria-hidden="true"
            data-description-filled
        />
    </button>
</template>
