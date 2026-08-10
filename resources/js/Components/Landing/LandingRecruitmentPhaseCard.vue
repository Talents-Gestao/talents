<script setup>
import { CheckBadgeIcon } from '@heroicons/vue/24/outline';
import { onMounted, onUnmounted, ref } from 'vue';

defineProps({
    phase: { type: Object, required: true },
    phaseIndex: { type: Number, required: true },
});

const isOpen = ref(false);
const prefersReducedMotion = ref(false);
const canHover = ref(true);

let motionMq;
let hoverMq;

const syncMedia = () => {
    prefersReducedMotion.value = Boolean(motionMq?.matches);
    canHover.value = Boolean(hoverMq?.matches);
    if (prefersReducedMotion.value) {
        isOpen.value = true;
    }
};

onMounted(() => {
    if (typeof window === 'undefined') {
        return;
    }
    motionMq = window.matchMedia('(prefers-reduced-motion: reduce)');
    hoverMq = window.matchMedia('(hover: hover) and (pointer: fine)');
    syncMedia();
    motionMq.addEventListener?.('change', syncMedia);
    hoverMq.addEventListener?.('change', syncMedia);
});

onUnmounted(() => {
    motionMq?.removeEventListener?.('change', syncMedia);
    hoverMq?.removeEventListener?.('change', syncMedia);
});

const toggle = () => {
    if (prefersReducedMotion.value) {
        return;
    }
    isOpen.value = !isOpen.value;
};

const onActivate = (event) => {
    if (canHover.value && event?.type === 'click' && event.detail > 0) {
        return;
    }
    toggle();
};

const onKeydown = (event) => {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        toggle();
    }
};
</script>

<template>
    <div class="recruitment-phase-wrap flex flex-col gap-3">
        <!--
          Shell clipa a moldura Uiverse (heavy-snake-69 / MIT).
          Pseudos atrás da face; proof fica fora do shell.
        -->
        <div
            class="recruitment-phase-shell"
            :class="{ 'is-open': isOpen }"
        >
            <span class="recruitment-phase-frame" aria-hidden="true" />
            <span class="recruitment-phase-glow" aria-hidden="true" />

            <article
                class="recruitment-phase-card"
                tabindex="0"
                role="button"
                :aria-expanded="isOpen || prefersReducedMotion ? 'true' : 'false'"
                :aria-controls="`recruitment-phase-panel-${phaseIndex}`"
                @click="onActivate"
                @keydown="onKeydown"
            >
                <div class="flex items-start justify-between gap-3">
                    <div
                        class="inline-flex rounded-2xl bg-gradient-to-br from-talents-50 to-talents-100 p-3 text-talents-800 ring-1 ring-talents-200/80"
                    >
                        <component :is="phase.icon" class="h-5 w-5" aria-hidden="true" />
                    </div>
                    <span
                        class="select-none text-2xl font-light tabular-nums leading-none tracking-tight text-slate-400 md:text-3xl"
                        aria-hidden="true"
                    >
                        {{ String(phaseIndex + 1).padStart(2, '0') }}
                    </span>
                </div>

                <h3 class="mt-5 text-xl font-bold tracking-tight text-slate-900">
                    {{ phase.title }}
                </h3>

                <p class="recruitment-phase-hint mt-2 text-xs font-medium tracking-wide text-slate-600">
                    <span class="recruitment-phase-hint-hover hidden">Passe o mouse para ver as etapas</span>
                    <span class="recruitment-phase-hint-touch">Toque para ver as etapas</span>
                </p>

                <div
                    :id="`recruitment-phase-panel-${phaseIndex}`"
                    class="recruitment-phase-reveal grid grid-rows-[0fr] transition-[grid-template-rows] duration-[400ms] ease-out"
                >
                    <div class="min-h-0 overflow-hidden">
                        <p class="recruitment-phase-desc mt-2 text-sm font-medium leading-relaxed text-talents-800">
                            {{ phase.description }}
                        </p>

                        <ol class="mt-6 space-y-3.5 border-t border-slate-200 pt-5">
                            <li
                                v-for="(step, stepIndex) in phase.steps"
                                :key="step.number"
                                class="recruitment-phase-step flex items-start gap-3 text-sm leading-snug text-slate-800"
                                :style="{ '--step-i': stepIndex }"
                            >
                                <span
                                    class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-talents-50 text-[0.7rem] font-bold tabular-nums text-talents-800 ring-1 ring-talents-200"
                                >
                                    {{ step.number }}
                                </span>
                                <span class="pt-0.5">{{ step.label }}</span>
                            </li>
                        </ol>
                    </div>
                </div>
            </article>
        </div>

        <p class="flex items-center gap-2 px-1 text-sm font-semibold text-slate-800">
            <CheckBadgeIcon class="h-5 w-5 shrink-0 text-talents-600" aria-hidden="true" />
            <span>{{ phase.proof }}</span>
        </p>
    </div>
</template>

<style scoped>
/*
 * Identidade: card branco + anel talents + moldura Uiverse no hover.
 *
 * Causa da “faixa” no topo: o frame em idle é um gradiente a cheio; no anel
 * fino do padding, o -45deg mostra um pedaço escuro só numa parte da largura.
 * Idle → anel sólido uniforme. Frame/glow com gradiente só no hover (clipados).
 */
.recruitment-phase-shell {
    --recruit-from: #632a7e; /* talents-600 */
    --recruit-to: #b388d9; /* talents-300 / accent */
    --recruit-glow: #7b4fa2; /* talents-500 */
    --recruit-ring: #7b4fa2; /* anel idle sólido — sem “slice” de gradiente */
    --recruit-pad: 2px;
    position: relative;
    z-index: 0;
    isolation: isolate;
    overflow: hidden;
    border-radius: calc(1.5rem + var(--recruit-pad));
    padding: var(--recruit-pad);
    /* Anel idle = fundo sólido do shell (uniforme em todo o perímetro) */
    background-color: var(--recruit-ring);
    transition: background-color 0.45s ease;
}

/* Gradiente + rotate: invisível no idle; ativo no hover/open */
.recruitment-phase-frame {
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: linear-gradient(-45deg, var(--recruit-from) 0%, var(--recruit-to) 100%);
    z-index: 0;
    pointer-events: none;
    opacity: 0;
    transform-origin: center center;
    transition:
        transform 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275),
        opacity 0.35s ease;
}

.recruitment-phase-glow {
    position: absolute;
    inset: var(--recruit-pad);
    border-radius: 1.5rem;
    background: linear-gradient(-45deg, var(--recruit-glow) 0%, var(--recruit-to) 100%);
    transform: translate3d(0, 0, 0) scale(0.96);
    filter: blur(16px);
    opacity: 0;
    z-index: 0;
    pointer-events: none;
    transition:
        filter 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275),
        opacity 0.45s ease;
}

.recruitment-phase-card {
    position: relative;
    z-index: 1;
    display: flex;
    width: 100%;
    flex-direction: column;
    border-radius: 1.5rem;
    background-color: #ffffff;
    padding: 1.25rem;
    cursor: pointer;
    outline: none;
    box-shadow:
        0 8px 24px -14px rgb(99 42 126 / 0.12),
        0 2px 6px rgb(15 23 42 / 0.04);
}

@media (min-width: 768px) {
    .recruitment-phase-card {
        padding: 1.5rem;
    }
}

.recruitment-phase-shell:is(:hover, .is-open, :focus-within) {
    /* No hover o anel sólido cede ao frame em gradiente */
    background-color: transparent;
}

.recruitment-phase-shell:is(:hover, .is-open, :focus-within) .recruitment-phase-frame {
    opacity: 1;
    transform: rotate(-90deg) scaleX(1.15) scaleY(0.88);
}

.recruitment-phase-shell:is(:hover, .is-open, :focus-within) .recruitment-phase-glow {
    filter: blur(22px);
    opacity: 0.4;
}

@media (prefers-reduced-motion: reduce) {
    .recruitment-phase-frame,
    .recruitment-phase-glow,
    .recruitment-phase-shell {
        transition: none !important;
    }

    .recruitment-phase-shell:is(:hover, .is-open, :focus-within) .recruitment-phase-frame {
        transform: none;
        opacity: 1;
    }

    .recruitment-phase-shell:is(:hover, .is-open, :focus-within) {
        background-color: var(--recruit-from);
    }

    .recruitment-phase-shell:is(:hover, .is-open, :focus-within) .recruitment-phase-glow {
        filter: blur(16px);
        opacity: 0.2;
    }
}
</style>
