<script setup>
import { CheckIcon, ChevronRightIcon, ExclamationTriangleIcon, MinusIcon } from '@heroicons/vue/20/solid';
import { computed } from 'vue';

/**
 * Barra compacta das etapas da gravação (Enviar → Confirmar falantes → Processar → Revisar relatório), numa linha
 * e com visual secundário, para não competir com o «Fluxo de finalização» (assinaturas). Só apresentação.
 * Estado de cada etapa: done | current | error | skipped | todo — dito por ícone e texto, não só pela cor.
 */
const props = defineProps({
    /** @type {Array<{ label: string, status: string }>} */
    steps: { type: Array, required: true },
});

const statusText = { done: 'concluída', current: 'etapa atual', error: 'com falha', skipped: 'não se aplica', todo: 'a fazer' };
const currentIndex = computed(() => props.steps.findIndex((s) => s.status === 'current' || s.status === 'error'));
</script>

<template>
    <nav aria-label="Etapas da gravação" class="min-w-0" data-feedback-recording-steps-indicator>
        <ol class="flex flex-nowrap items-center gap-x-1 gap-y-1.5 text-[13px] leading-5 sm:flex-wrap">
            <li v-for="(step, i) in steps" :key="step.label" class="flex items-center gap-1" :aria-current="i === currentIndex ? 'step' : undefined">
                <span
                    class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5"
                    :class="{
                        'bg-talents-50 font-semibold text-talents-900 ring-1 ring-talents-200': step.status === 'current',
                        'bg-rose-50 font-semibold text-rose-800 ring-1 ring-rose-200': step.status === 'error',
                        'text-slate-700': step.status === 'done',
                        'text-slate-600': step.status === 'todo' || step.status === 'skipped',
                    }"
                >
                    <span
                        class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full text-[10px] font-bold"
                        :class="{
                            'bg-talents-600 text-white': step.status === 'current',
                            'bg-rose-600 text-white': step.status === 'error',
                            'bg-emerald-600 text-white': step.status === 'done',
                            'bg-slate-200 text-slate-700': step.status === 'todo' || step.status === 'skipped',
                        }"
                        aria-hidden="true"
                    >
                        <CheckIcon v-if="step.status === 'done'" class="h-3 w-3" />
                        <ExclamationTriangleIcon v-else-if="step.status === 'error'" class="h-2.5 w-2.5" />
                        <MinusIcon v-else-if="step.status === 'skipped'" class="h-3 w-3" />
                        <template v-else>{{ i + 1 }}</template>
                    </span>
                    <!-- No celular, só a etapa atual mostra o nome (as demais ficam para o leitor de tela): cabe numa linha. -->
                    <span :class="[step.status === 'skipped' ? 'line-through decoration-slate-400' : '', i === currentIndex ? '' : 'sr-only sm:not-sr-only']">{{ step.label }}</span>
                    <span class="sr-only">({{ statusText[step.status] ?? statusText.todo }})</span>
                    <span v-if="step.status === 'skipped'" class="hidden text-slate-600 sm:inline" aria-hidden="true">(não se aplica)</span>
                </span>
                <ChevronRightIcon v-if="i < steps.length - 1" class="hidden h-3.5 w-3.5 shrink-0 text-slate-400 sm:block" aria-hidden="true" />
            </li>
        </ol>
    </nav>
</template>
