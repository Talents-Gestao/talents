<script setup>
import { Link } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
import { FRENTES } from './landingContent.js';
import { onTablistKeydown, useReveal } from './useLandingMotion.js';
import './landingGlass.css';

/**
 * Frentes Empresas × Pessoas: abas ARIA (tablist/tab/tabpanel). Os dois painéis ficam no DOM
 * (o inativo com `hidden`), então todo o texto continua indexável.
 */
const root = ref(null);
const tabs = ref([]);
const active = ref(0);

useReveal(root);

const select = async (i, focus = true) => {
    active.value = i;
    if (focus) {
        await nextTick();
        tabs.value[i]?.focus();
    }
};
</script>

<template>
    <section ref="root" class="lg-stage scroll-mt-24 py-14 md:py-20" aria-labelledby="frentes-title">
        <div class="mx-auto max-w-6xl px-4">
            <h2
                id="frentes-title"
                class="lg-reveal max-w-3xl text-3xl font-bold leading-tight tracking-tight text-talents-900 md:text-5xl"
            >
                Para Empresas e Para Pessoas
            </h2>

            <div
                role="tablist"
                aria-label="Frentes de atuação"
                class="lg-reveal lg-glass lg-glass--soft mt-8 inline-grid grid-cols-2 gap-1 p-1.5"
                style="border-radius: 999px"
            >
                <button
                    v-for="(f, i) in FRENTES"
                    :id="`frente-tab-${f.id}`"
                    :key="f.id"
                    :ref="(el) => (tabs[i] = el)"
                    type="button"
                    role="tab"
                    class="lg-tab"
                    :aria-selected="active === i"
                    :aria-controls="`frente-panel-${f.id}`"
                    :tabindex="active === i ? 0 : -1"
                    @click="select(i, false)"
                    @keydown="onTablistKeydown($event, FRENTES.length, active, select)"
                >
                    {{ f.label }}
                </button>
            </div>

            <div class="mt-6">
                <div
                    v-for="(f, i) in FRENTES"
                    :id="`frente-panel-${f.id}`"
                    :key="f.id"
                    role="tabpanel"
                    :aria-labelledby="`frente-tab-${f.id}`"
                    :hidden="active !== i"
                    class="lg-panel grid gap-6 lg:grid-cols-12 lg:items-stretch"
                >
                    <div class="lg-glass flex flex-col p-7 md:p-10 lg:col-span-7">
                        <p v-if="f.lead" class="text-base font-medium text-talents-700 md:text-lg">{{ f.lead }}</p>
                        <h3 class="mt-2 text-3xl font-bold tracking-tight text-talents-900 md:text-4xl lg:text-5xl">
                            {{ f.title }}
                        </h3>
                        <p class="mt-5 max-w-2xl text-base leading-relaxed text-slate-700 md:text-lg lg:text-xl lg:leading-relaxed">{{ f.text }}</p>
                        <div class="mt-auto pt-8">
                            <Link :href="route(f.route)" class="lg-btn">{{ f.cta }}</Link>
                        </div>
                    </div>

                    <ul class="grid content-start gap-4 sm:grid-cols-2 lg:col-span-5 lg:grid-cols-1" :class="{ 'lg:grid-cols-2': f.items.length === 4 }">
                        <li
                            v-for="item in f.items"
                            :key="item.title"
                            class="lg-glass lg-lift p-5"
                        >
                            <h4 class="text-lg font-semibold text-talents-900">{{ item.title }}</h4>
                            <p class="mt-2 text-sm leading-relaxed text-slate-700">{{ item.description }}</p>
                        </li>
                        <li v-if="f.extra" class="lg-glass p-5 sm:col-span-2">
                            <h4 class="text-lg font-semibold text-talents-900">{{ f.extra.title }}</h4>
                            <p class="mt-2 text-sm leading-relaxed text-slate-700">{{ f.extra.text }}</p>
                            <Link :href="route(f.extra.route)" class="lg-link mt-3 inline-block">{{ f.extra.label }}</Link>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.lg-tab {
    min-height: 2.75rem;
    border-radius: 999px;
    padding: 0.5rem 1.6rem;
    font-size: 0.95rem;
    font-weight: 600;
    color: #4a2070;
    transition:
        background-color 0.25s ease,
        color 0.25s ease,
        box-shadow 0.25s ease;
}

.lg-tab[aria-selected='true'] {
    background: #632a7e;
    color: #fff;
    box-shadow: 0 8px 18px -10px rgba(74, 32, 112, 0.7);
}

.lg-tab[aria-selected='false']:hover {
    background: rgba(179, 136, 217, 0.18);
}

/* O `grid` do Tailwind anularia o atributo hidden do painel inativo. */
.lg-panel[hidden] {
    display: none;
}

/* Entrada do painel ao trocar de aba (só transform/opacity). */
.lg-panel:not([hidden]) > * {
    animation: lg-panel-in 0.45s ease both;
}

.lg-panel:not([hidden]) > ul > li:nth-child(2) {
    animation: lg-panel-in 0.45s 70ms ease both;
}

@keyframes lg-panel-in {
    from {
        opacity: 0;
        transform: translateY(14px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .lg-panel:not([hidden]) > *,
    .lg-panel:not([hidden]) > ul > li:nth-child(2) {
        animation: none;
    }
}

@media (min-width: 1024px) {
    .lg-panel {
        min-height: 25rem;
    }
}
</style>
