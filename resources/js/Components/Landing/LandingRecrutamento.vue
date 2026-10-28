<script setup>
import { Link } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
import { RECRUTAMENTO_INTRO } from './landingContent.js';
import { onTablistKeydown, useReveal } from './useLandingMotion.js';
import './landingGlass.css';

/**
 * Recrutamento estratégico: stepper horizontal comum (sem prender a rolagem). No desktop o stepper é um
 * tablist por teclado com um painel por fase; no mobile as 4 fases aparecem empilhadas, todas visíveis.
 */
defineProps({
    phases: { type: Array, required: true },
});

const root = ref(null);
const tabs = ref([]);
const active = ref(0);

useReveal(root);

const select = async (i, focus = false) => {
    active.value = i;
    if (focus) {
        await nextTick();
        tabs.value[i]?.focus();
    }
};
</script>

<template>
    <section
        id="recrutamento-estrategico"
        ref="root"
        class="lg-band scroll-mt-24 py-16 md:py-24"
        aria-labelledby="recrutamento-title"
    >
        <div class="mx-auto max-w-6xl px-4">
            <div class="lg-reveal max-w-3xl">
                <Link :href="route('landing.contratacao')" class="lg-link text-sm">
                    {{ RECRUTAMENTO_INTRO.label }} →
                </Link>
                <h2
                    id="recrutamento-title"
                    class="mt-2 text-3xl font-bold leading-tight tracking-tight text-talents-900 md:text-5xl"
                >
                    {{ RECRUTAMENTO_INTRO.title }}
                </h2>
                <p class="mt-4 text-base leading-relaxed text-slate-700 md:text-lg">{{ RECRUTAMENTO_INTRO.text }}</p>
            </div>

            <!-- Stepper (desktop) -->
            <div
                role="tablist"
                aria-label="Fases do recrutamento"
                class="lg-reveal relative mt-12 hidden grid-cols-4 gap-4 lg:grid"
            >
                <span class="lg-rail" aria-hidden="true" />
                <button
                    v-for="(p, i) in phases"
                    :id="`fase-tab-${i}`"
                    :key="p.title"
                    :ref="(el) => (tabs[i] = el)"
                    type="button"
                    role="tab"
                    class="lg-step"
                    :aria-selected="active === i"
                    :aria-controls="`fase-panel-${i}`"
                    :tabindex="active === i ? 0 : -1"
                    @click="select(i)"
                    @keydown="onTablistKeydown($event, phases.length, active, (n) => select(n, true))"
                >
                    <span class="lg-step__dot">
                        <component :is="p.icon" class="h-5 w-5" aria-hidden="true" />
                    </span>
                    <span class="lg-step__label">{{ p.title }}</span>
                </button>
            </div>

            <div class="mt-8 grid gap-6 lg:mt-6">
                <article
                    v-for="(p, i) in phases"
                    :id="`fase-panel-${i}`"
                    :key="p.title"
                    role="tabpanel"
                    :aria-labelledby="`fase-tab-${i}`"
                    class="lg-glass lg-reveal grid gap-6 p-6 md:p-9 lg:grid-cols-12"
                    :class="{ 'lg:hidden': active !== i }"
                >
                    <div class="lg:col-span-5">
                        <div class="flex items-center gap-3 lg:hidden">
                            <span class="lg-step__dot lg-step__dot--on">
                                <component :is="p.icon" class="h-5 w-5" aria-hidden="true" />
                            </span>
                        </div>
                        <h3 class="mt-4 text-3xl font-bold tracking-tight text-talents-900 lg:mt-0 lg:text-4xl">
                            {{ p.title }}
                        </h3>
                        <p class="mt-3 text-lg leading-relaxed text-slate-700">{{ p.description }}</p>
                        <p class="lg-proof mt-6">{{ p.proof }}</p>
                    </div>
                    <ol class="grid content-start gap-3 lg:col-span-7">
                        <li
                            v-for="step in p.steps"
                            :key="step.number"
                            class="lg-glass lg-glass--soft flex items-start gap-4 p-4"
                        >
                            <span class="lg-num" aria-hidden="true">{{ step.number }}</span>
                            <span class="pt-0.5 text-base font-medium text-talents-900">{{ step.label }}</span>
                        </li>
                    </ol>
                </article>
            </div>

            <div class="lg-reveal mt-10 flex flex-wrap items-center gap-4">
                <Link :href="route('landing.contato')" class="lg-btn">Quero contratar com a Talents</Link>
            </div>
        </div>
    </section>
</template>

<style scoped>
.lg-band {
    background: linear-gradient(180deg, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, 0.7) 12%, rgba(255, 255, 255, 0.7) 88%, rgba(255, 255, 255, 0) 100%);
}

.lg-rail {
    position: absolute;
    left: 12.5%;
    right: 12.5%;
    top: 1.65rem;
    height: 3px;
    border-radius: 3px;
    background: linear-gradient(90deg, #d4b8e4, #9b6bc4, #d4b8e4);
    opacity: 0.7;
}

.lg-step {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.7rem;
    padding: 0 0.5rem 0.5rem;
    border-radius: 1rem;
    color: #4a2070;
    font-weight: 600;
    text-align: center;
}

.lg-step:focus-visible {
    outline: 2px solid #7b4fa2;
    outline-offset: 4px;
}

.lg-step__dot {
    display: inline-flex;
    height: 3.3rem;
    width: 3.3rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.9);
    color: #632a7e;
    border: 1px solid rgba(99, 42, 126, 0.18);
    box-shadow: 0 10px 22px -14px rgba(74, 32, 112, 0.6);
    transition:
        background-color 0.25s ease,
        color 0.25s ease,
        transform 0.25s ease;
}

.lg-step__dot--on,
.lg-step[aria-selected='true'] .lg-step__dot {
    background: #632a7e;
    color: #fff;
}

@media (hover: hover) {
    .lg-step:hover .lg-step__dot {
        transform: translateY(-2px);
    }
}

.lg-step__label {
    font-size: 1.05rem;
}

.lg-num {
    display: inline-flex;
    height: 1.9rem;
    width: 1.9rem;
    flex: none;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: rgba(179, 136, 217, 0.22);
    color: #4a2070;
    font-size: 0.85rem;
    font-weight: 700;
}

.lg-proof {
    display: inline-block;
    border-radius: 999px;
    background: rgba(179, 136, 217, 0.2);
    padding: 0.45rem 1rem;
    color: #3a1858;
    font-size: 0.95rem;
    font-weight: 600;
}

.lg-reveal + .lg-reveal {
    --lg-delay: 70ms;
}
</style>
