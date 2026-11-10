<script setup>
import { ChevronDownIcon } from '@heroicons/vue/24/outline';
import { computed, ref, useId } from 'vue';

/**
 * Descrição do quadro na listagem: oculta por padrão, expandida só neste item.
 * Fica acima do link que cobre o card (relative z-10), então o clique não abre o quadro.
 */
const props = defineProps({
    description: { type: String, default: '' },
});

const expanded = ref(false);
const panelId = `board-description-${useId()}`;
const text = computed(() => (props.description || '').trim());
</script>

<template>
    <div v-if="text" class="relative z-10 mt-0.5">
        <button
            type="button"
            class="inline-flex items-center gap-1 rounded text-xs font-medium text-talents-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500"
            :aria-expanded="expanded ? 'true' : 'false'"
            :aria-controls="panelId"
            @click.stop.prevent="expanded = !expanded"
        >
            <ChevronDownIcon class="h-3.5 w-3.5 transition-transform motion-reduce:transition-none" :class="expanded ? 'rotate-180' : ''" aria-hidden="true" />
            {{ expanded ? 'Ocultar descrição' : 'Ver descrição' }}
        </button>
        <p v-show="expanded" :id="panelId" class="mt-1 whitespace-pre-line break-words text-xs text-slate-600">{{ text }}</p>
    </div>
</template>
