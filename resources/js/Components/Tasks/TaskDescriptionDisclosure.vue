<script setup>
import { ChevronDownIcon } from '@heroicons/vue/24/outline';
import { ref, useId } from 'vue';

/**
 * Descrição recolhível (mesmo visual do «Ver descrição» dos cartões e quadros).
 * O conteúdo fica só oculto (v-show) ao recolher: o que foi digitado continua no campo e é salvo normalmente.
 */
const props = defineProps({
    /** Há texto na descrição: muda o rótulo de «Adicionar descrição» para «Ver descrição». */
    filled: { type: Boolean, default: false },
    /** Começa expandido (ex.: o item já tem descrição). */
    defaultOpen: { type: Boolean, default: false },
    /** Sem edição: sem a opção «Adicionar descrição». */
    readOnly: { type: Boolean, default: false },
});

const open = ref(props.defaultOpen);
const panelId = `task-description-${useId()}`;
</script>

<template>
    <div v-if="filled || !readOnly">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <button
                type="button"
                class="inline-flex items-center gap-1 rounded text-[11px] font-medium text-talents-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500"
                :aria-expanded="open ? 'true' : 'false'"
                :aria-controls="panelId"
                data-description-toggle
                @click="open = !open"
            >
                <ChevronDownIcon
                    class="h-3.5 w-3.5 transition-transform motion-reduce:transition-none"
                    :class="open ? 'rotate-180' : ''"
                    aria-hidden="true"
                />
                {{ open ? 'Ocultar descrição' : filled ? 'Ver descrição' : 'Adicionar descrição' }}
            </button>
            <slot name="aside" />
        </div>
        <div v-show="open" :id="panelId" class="mt-1">
            <slot />
        </div>
    </div>
    <div v-else-if="$slots.aside" class="flex flex-wrap items-center gap-x-3">
        <slot name="aside" />
    </div>
</template>
