<script setup>
import { CheckIcon, ChevronRightIcon } from '@heroicons/vue/20/solid';

/**
 * Jornada do feedback numa linha (padrão Progress indicator): Preenchimento X/Y › Assinaturas › Concluído.
 * Estado em ícone e texto, não só pela cor. Abaixo de 768 px vira uma linha compacta («Preenchimento 12/42 · 1 de 3»).
 */
defineProps({
    /** journey() de feedbackSessionView. */
    journey: { type: Object, required: true },
});

const statusText = { done: 'concluída', current: 'etapa atual', todo: 'a fazer' };
</script>

<template>
    <nav aria-label="Jornada do feedback" data-feedback-journey>
        <p class="text-sm font-medium text-slate-700 md:hidden" data-feedback-journey-compact>{{ journey.compact }}</p>
        <ol class="hidden flex-wrap items-center gap-x-2 gap-y-1 text-sm md:flex">
            <li v-for="(step, i) in journey.steps" :key="step.key" class="flex items-center gap-2" :aria-current="step.status === 'current' ? 'step' : undefined">
                <span
                    class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[11px] font-bold"
                    :class="{
                        'bg-emerald-600 text-white': step.status === 'done',
                        'bg-talents-600 text-white': step.status === 'current',
                        'bg-slate-200 text-slate-700': step.status === 'todo',
                    }"
                    aria-hidden="true"
                >
                    <CheckIcon v-if="step.status === 'done'" class="h-3.5 w-3.5" />
                    <template v-else>{{ i + 1 }}</template>
                </span>
                <span :class="step.status === 'current' ? 'font-semibold text-slate-900' : 'text-slate-700'">
                    {{ step.label }}<span v-if="step.detail" class="ml-1 tabular-nums">{{ step.detail }}</span>
                </span>
                <span class="sr-only">({{ statusText[step.status] }})</span>
                <ChevronRightIcon v-if="i < journey.steps.length - 1" class="h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" />
            </li>
        </ol>
    </nav>
</template>
