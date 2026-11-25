<script setup>
import { journeyConnectors } from '@/utils/feedbackSessionView';
import { computed } from 'vue';

/**
 * Jornada do feedback (padrão Progress indicator, como wizard): Preenchimento X/Y — Assinaturas — Concluído, com
 * bolinhas numeradas ligadas por conectores. Estado em ícone, número e texto (sr-only), não só pela cor.
 * - Atual: anel fixo e halo que pulsa devagar; título em negrito; contador e barra abaixo do título.
 * - Concluída: bolinha preenchida com check que «desenha» ao entrar.
 * - A fazer: bolinha vazia, neutra.
 * - Conector: enche da esquerda para a direita; no da etapa atual, na proporção do preenchimento.
 * Animações só em CSS (transform, opacity e o traço do check), escalonadas por etapa para mostrar a passagem de uma
 * para a outra; com prefers-reduced-motion, nada anima e o estado aparece direto. Abaixo de 768 px vira uma linha
 * compacta («Preenchimento 12/42 · 1 de 3») com barra segmentada. Só apresentação.
 */
const props = defineProps({
    /** journey() de feedbackSessionView. */
    journey: { type: Object, required: true },
    /** overallProgress() de feedbackSessionView (opcional): barra do preenchimento na etapa atual. */
    progress: { type: Object, default: null },
});

const statusText = { done: 'concluída', current: 'etapa atual', todo: 'a fazer' };

/** Percentual do preenchimento, só quando a etapa atual é o preenchimento e há perguntas. */
const fillPercent = computed(() => {
    const total = props.progress?.total ?? 0;
    if (!total || props.journey.steps[0]?.status !== 'current') return null;
    return Math.min(100, Math.round(((props.progress.answered ?? 0) / total) * 100));
});

const connectors = computed(() => journeyConnectors(props.journey.steps, fillPercent.value));

/** Escalonamento da entrada: check da etapa i, depois o conector i, e por fim o halo da etapa atual. */
const STAGGER_MS = 220;
const delay = (ms) => ({ '--journey-delay': `${ms}ms` });
</script>

<template>
    <nav aria-label="Jornada do feedback" class="rounded-2xl border border-slate-200/80 bg-white px-4 py-5 shadow-sm sm:px-6" data-feedback-journey>
        <!-- Celular: texto compacto + barra segmentada -->
        <div class="space-y-2.5 md:hidden">
            <p class="text-sm font-semibold text-slate-900" data-feedback-journey-compact>{{ journey.compact }}</p>
            <div class="flex gap-1.5" aria-hidden="true">
                <span
                    v-for="step in journey.steps"
                    :key="step.key"
                    class="h-1.5 flex-1 rounded-full transition-colors duration-500 motion-reduce:transition-none"
                    :class="{
                        'bg-emerald-500': step.status === 'done',
                        'bg-talents-600': step.status === 'current',
                        'bg-slate-200': step.status === 'todo',
                    }"
                />
            </div>
        </div>

        <!--
            Desktop: cada etapa é uma linha «bolinha + título» (o título tem a altura da bolinha, leading-8 = h-8, então
            os dois ficam centralizados entre si); contador e barra vêm logo abaixo do título, alinhados à esquerda com
            ele. Os conectores são itens próprios (ocultos do leitor de tela) que dividem igualmente o espaço entre as
            etapas e ficam presos ao centro da bolinha (mt-[14px] + h-1 = 16 px = metade de h-8), não à altura do texto.
        -->
        <ol class="hidden items-start md:flex">
            <template v-for="(step, i) in journey.steps" :key="step.key">
                <li
                    class="flex flex-none items-start"
                    :aria-current="step.status === 'current' ? 'step' : undefined"
                    :data-feedback-journey-step="step.status"
                >
                    <span class="relative flex h-8 w-8 shrink-0" aria-hidden="true">
                        <!-- Halo da etapa atual: expande e some, em loop lento (desligado com movimento reduzido) -->
                        <span
                            v-if="step.status === 'current'"
                            class="journey-halo pointer-events-none absolute inset-0 rounded-full bg-talents-400"
                            :style="delay(i * STAGGER_MS + 600)"
                        />
                        <span
                            class="relative flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold transition-colors duration-500 motion-reduce:transition-none"
                            :class="{
                                'bg-emerald-600 text-white': step.status === 'done',
                                'bg-talents-600 text-white ring-4 ring-talents-100': step.status === 'current',
                                'border border-slate-300 bg-white text-slate-600': step.status === 'todo',
                            }"
                        >
                            <svg
                                v-if="step.status === 'done'"
                                class="journey-check h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="3"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                :style="delay(i * STAGGER_MS)"
                            >
                                <path pathLength="1" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                            <template v-else>{{ i + 1 }}</template>
                        </span>
                    </span>
                    <span class="ml-3 flex flex-col">
                        <span
                            class="whitespace-nowrap text-sm leading-8 transition-colors duration-500 motion-reduce:transition-none"
                            :class="{
                                'font-semibold text-slate-900': step.status === 'current',
                                'font-medium text-slate-700': step.status === 'done',
                                'text-slate-600': step.status === 'todo',
                            }"
                        >{{ step.label }}</span>
                        <span v-if="step.detail" class="-mt-1 whitespace-nowrap text-xs leading-4 tabular-nums text-slate-600">{{ step.detail }}</span>
                        <!-- Trilho com a largura do bloco de texto (mínimo 8rem) e borda própria; preenchimento proporcional -->
                        <span
                            v-if="i === 0 && fillPercent !== null"
                            class="mt-2 block h-2 w-full min-w-[8rem] overflow-hidden rounded-full bg-slate-200 ring-1 ring-inset ring-slate-300/70"
                            aria-hidden="true"
                            data-feedback-journey-fill
                        >
                            <span class="block h-full rounded-full bg-talents-600 transition-[width] duration-500 motion-reduce:transition-none" :style="{ width: `${fillPercent}%` }" />
                        </span>
                        <span class="sr-only">({{ statusText[step.status] }})</span>
                    </span>
                </li>
                <li
                    v-if="connectors[i]"
                    class="relative mx-4 mt-[14px] h-1 min-w-[1.5rem] flex-1 overflow-hidden rounded-full bg-slate-200"
                    aria-hidden="true"
                    data-feedback-journey-connector
                    :data-feedback-journey-connector-tone="connectors[i].tone"
                >
                    <span
                        class="journey-connector-fill absolute inset-0 origin-left rounded-full"
                        :class="connectors[i].tone === 'done' ? 'bg-emerald-500' : 'bg-talents-600'"
                        :style="{ transform: `scaleX(${connectors[i].fill})`, ...delay(i * STAGGER_MS + 150) }"
                    />
                </li>
            </template>
        </ol>
    </nav>
</template>

<style scoped>
/* Estado final sem animação (vale também com prefers-reduced-motion: troca de estado imediata). */
.journey-halo {
    opacity: 0;
}
.journey-check path {
    stroke-dasharray: 1;
    stroke-dashoffset: 0;
}

@media (prefers-reduced-motion: no-preference) {
    /* Atual: halo que expande e some, devagar. */
    .journey-halo {
        animation: journey-halo 2.6s cubic-bezier(0, 0, 0.2, 1) var(--journey-delay, 0ms) infinite;
    }

    /* Concluída: check entra com scale-in curto e o traço «desenha». */
    .journey-check {
        animation: journey-pop 320ms cubic-bezier(0.34, 1.56, 0.64, 1) var(--journey-delay, 0ms) backwards;
    }
    .journey-check path {
        animation: journey-draw 420ms ease-out calc(var(--journey-delay, 0ms) + 80ms) backwards;
    }

    /* Conector: enche da esquerda para a direita ao entrar e acompanha mudanças de etapa/progresso. */
    .journey-connector-fill {
        transition:
            transform 700ms cubic-bezier(0.22, 1, 0.36, 1),
            background-color 500ms ease;
        animation: journey-fill 700ms cubic-bezier(0.22, 1, 0.36, 1) var(--journey-delay, 0ms) backwards;
    }
}

@keyframes journey-halo {
    0% {
        transform: scale(1);
        opacity: 0.45;
    }
    70%,
    100% {
        transform: scale(1.9);
        opacity: 0;
    }
}

@keyframes journey-pop {
    from {
        transform: scale(0.4);
        opacity: 0;
    }
}

@keyframes journey-draw {
    from {
        stroke-dashoffset: 1;
    }
}

@keyframes journey-fill {
    from {
        transform: scaleX(0);
    }
}
</style>
