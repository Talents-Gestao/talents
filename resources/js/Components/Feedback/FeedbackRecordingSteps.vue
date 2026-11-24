<script setup>
import { CheckIcon, ChevronRightIcon, ExclamationTriangleIcon, MinusIcon } from '@heroicons/vue/20/solid';
import { computed, ref } from 'vue';

/**
 * Barra compacta das etapas da gravação (Enviar → Confirmar falantes → Processar → Revisar relatório), numa linha
 * e com visual secundário, para não competir com o «Fluxo de finalização» (assinaturas). Só apresentação.
 * Estado de cada etapa: done | current | error | skipped | todo — dito por ícone e texto, não só pela cor.
 * Voltar etapas: com `options` (rewindOptions), etapas concluídas clicáveis viram botões («Voltar para …») e emitem
 * `rewind`; concluídas indisponíveis mostram o motivo em tooltip. A atual e as futuras nunca são clicáveis.
 * Em repouso o visual é o mesmo; a área de toque cresce por fora (44 px) sem mudar o desenho.
 */
const props = defineProps({
    /** @type {Array<{ key: string, label: string, status: string }>} */
    steps: { type: Array, required: true },
    /** @type {Array<{ clickable: boolean, reason: string }>} */
    options: { type: Array, default: () => [] },
});

const emit = defineEmits(['rewind']);

const option = (i) => props.options[i] ?? { clickable: false, reason: '' };
const interactive = (i) => option(i).clickable || !!option(i).reason;
const tipId = (i) => `feedback_recording_step_tip_${i}`;

/** Toque ou clique numa etapa bloqueada mostra o motivo (no celular não há hover); some ao sair ou com Esc. */
const tipOpen = ref(-1);
function onClick(step, i) {
    if (option(i).clickable) emit('rewind', step.key);
    else if (option(i).reason) tipOpen.value = tipOpen.value === i ? -1 : i;
}
function closeTip() {
    tipOpen.value = -1;
}

const statusText = { done: 'concluída', current: 'etapa atual', error: 'com falha', skipped: 'não se aplica', todo: 'a fazer' };
const currentIndex = computed(() => props.steps.findIndex((s) => s.status === 'current' || s.status === 'error'));
</script>

<template>
    <nav aria-label="Etapas da gravação" class="min-w-0" data-feedback-recording-steps-indicator>
        <ol class="flex flex-nowrap items-center gap-x-1 gap-y-1.5 text-[13px] leading-5 sm:flex-wrap">
            <li v-for="(step, i) in steps" :key="step.label" class="flex items-center gap-1" :aria-current="i === currentIndex ? 'step' : undefined">
                <component
                    :is="interactive(i) ? 'button' : 'span'"
                    :type="interactive(i) ? 'button' : undefined"
                    :aria-label="interactive(i) ? `Voltar para ${step.label}` : undefined"
                    :aria-disabled="interactive(i) && !option(i).clickable ? 'true' : undefined"
                    :aria-describedby="option(i).reason ? tipId(i) : undefined"
                    :data-feedback-step-rewind="interactive(i) ? step.key : undefined"
                    class="group/step relative inline-flex items-center gap-1.5 rounded-full px-2 py-0.5"
                    :class="[
                        interactive(i)
                            ? 'before:absolute before:-inset-x-1.5 before:-inset-y-3 sm:before:-inset-y-2.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-600 focus-visible:ring-offset-1'
                            : '',
                        option(i).clickable ? 'cursor-pointer transition hover:bg-white hover:text-slate-900 hover:shadow-sm hover:ring-1 hover:ring-slate-200' : '',
                        interactive(i) && !option(i).clickable ? 'cursor-not-allowed' : '',
                        {
                        'bg-talents-50 font-semibold text-talents-900 ring-1 ring-talents-200': step.status === 'current',
                        'bg-rose-50 font-semibold text-rose-800 ring-1 ring-rose-200': step.status === 'error',
                        'text-slate-700': step.status === 'done',
                        'text-slate-600': step.status === 'todo' || step.status === 'skipped',
                        },
                    ]"
                    @click="onClick(step, i)"
                    @blur="closeTip"
                    @mouseleave="closeTip"
                    @keydown.esc="closeTip"
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
                    <!-- Motivo do bloqueio: aparece ao passar o mouse ou focar (teclado). -->
                    <span
                        v-if="option(i).reason"
                        :id="tipId(i)"
                        role="tooltip"
                        class="pointer-events-none absolute left-0 top-full z-30 mt-2 w-64 max-w-[calc(100vw-2rem)] whitespace-normal rounded-lg bg-slate-900 px-3 py-2 text-left text-xs font-medium leading-snug text-white shadow-lg group-hover/step:block group-focus-visible/step:block"
                        :class="tipOpen === i ? 'block' : 'hidden'"
                    >
                        {{ option(i).reason }}
                    </span>
                </component>
                <ChevronRightIcon v-if="i < steps.length - 1" class="hidden h-3.5 w-3.5 shrink-0 text-slate-400 sm:block" aria-hidden="true" />
            </li>
        </ol>
    </nav>
</template>
