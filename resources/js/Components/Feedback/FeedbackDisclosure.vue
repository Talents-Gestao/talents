<script setup>
import { ChevronDownIcon } from '@heroicons/vue/24/outline';

/**
 * Bloco recolhível de Feedbacks (textos longos: transparência, passo a passo, detalhes técnicos).
 * <details>/<summary> nativos: abre e fecha por teclado (Enter/Espaço) e o leitor de tela anuncia o estado.
 */
defineProps({
    label: { type: String, required: true },
    /** Linha curta sempre visível ao lado do rótulo. */
    hint: { type: String, default: '' },
    tone: { type: String, default: 'neutral' },
});
</script>

<template>
    <details
        class="group rounded-xl border text-sm"
        :class="tone === 'brand' ? 'border-talents-100 bg-talents-50/50' : 'border-slate-200 bg-slate-50/60'"
    >
        <summary
            class="flex min-h-[44px] cursor-pointer list-none items-center gap-2 rounded-xl px-4 py-2.5 font-semibold text-slate-800 marker:content-none hover:bg-white/70 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500 focus-visible:ring-offset-1 [&::-webkit-details-marker]:hidden"
        >
            <slot name="icon" />
            <span class="min-w-0 flex-1">
                {{ label }}
                <span v-if="hint" class="block text-[13px] font-normal text-slate-700">{{ hint }}</span>
            </span>
            <ChevronDownIcon class="h-4 w-4 shrink-0 text-slate-500 transition-transform group-open:rotate-180 motion-reduce:transition-none" aria-hidden="true" />
        </summary>
        <div class="px-4 pb-4 pt-1 text-slate-700">
            <slot />
        </div>
    </details>
</template>
